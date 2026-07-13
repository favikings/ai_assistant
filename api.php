<?php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gmail.php';

header('Content-Type: application/json');

// Rate limiting via session
if (!isset($_SESSION['api_calls'])) {
    $_SESSION['api_calls'] = [];
}

$now = time();
$_SESSION['api_calls'] = array_filter($_SESSION['api_calls'], fn($t) => $t > $now - RATE_LIMIT_WINDOW);
if (count($_SESSION['api_calls']) >= RATE_LIMIT_MAX) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Please wait a moment.']);
    exit;
}
$_SESSION['api_calls'][] = $now;

// Handle history request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'history') {
    $db = get_db();
    $stmt = $db->prepare("SELECT role, content FROM chat_history ORDER BY created_at ASC LIMIT ?");
    $stmt->execute([CHAT_CONTEXT_LIMIT]);
    echo json_encode(['messages' => $stmt->fetchAll()]);
    exit;
}

// Handle chat message
$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty message']);
    exit;
}

$db = get_db();

// Save user message
$stmt = $db->prepare("INSERT INTO chat_history (role, content) VALUES ('user', ?)");
$stmt->execute([$message]);

// Build conversation context
$stmt = $db->prepare("SELECT role, content FROM chat_history ORDER BY created_at ASC LIMIT ?");
$stmt->execute([CHAT_CONTEXT_LIMIT]);
$history = $stmt->fetchAll();

$contents = [];
foreach ($history as $msg) {
    $contents[] = [
        'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
        'parts' => [['text' => $msg['content']]]
    ];
}

// System instruction
$system_instruction = [
    'parts' => [['text' => "You are a personal virtual assistant. You help with tasks, goals, reminders, Gmail inbox management, and general conversation. Be concise and helpful. When the user asks you to do something that matches a tool, use the tool. Always respond in a natural, conversational way after getting tool results. Don't mention tool names or technical details to the user."]]
];

// Tool definitions
$tools = [
    'function_declarations' => [
        [
            'name' => 'add_task',
            'description' => 'Add a new task with a title, optional priority (low/medium/high), and optional due date.',
            'parameters' => [
                'type' => 'OBJECT',
                'properties' => [
                    'title' => ['type' => 'STRING', 'description' => 'Task title'],
                    'priority' => ['type' => 'STRING', 'description' => 'Priority level', 'enum' => ['low', 'medium', 'high']],
                    'due_date' => ['type' => 'STRING', 'description' => 'Due date in YYYY-MM-DD format'],
                ],
                'required' => ['title'],
            ],
        ],
        [
            'name' => 'list_tasks',
            'description' => 'List open tasks sorted by priority then due date.',
        ],
        [
            'name' => 'complete_task',
            'description' => 'Mark a task as done. Match by title or ID.',
            'parameters' => [
                'type' => 'OBJECT',
                'properties' => [
                    'task_id' => ['type' => 'INTEGER', 'description' => 'Task ID'],
                    'title' => ['type' => 'STRING', 'description' => 'Task title to match (partial match)'],
                ],
            ],
        ],
        [
            'name' => 'add_goal',
            'description' => 'Add a new goal with a title and optional target date.',
            'parameters' => [
                'type' => 'OBJECT',
                'properties' => [
                    'title' => ['type' => 'STRING', 'description' => 'Goal title'],
                    'target_date' => ['type' => 'STRING', 'description' => 'Target date in YYYY-MM-DD format'],
                ],
                'required' => ['title'],
            ],
        ],
        [
            'name' => 'list_goals',
            'description' => 'List all active goals.',
        ],
        [
            'name' => 'update_goal_progress',
            'description' => 'Add a progress note to an existing goal. Appends to the running log.',
            'parameters' => [
                'type' => 'OBJECT',
                'properties' => [
                    'goal_id' => ['type' => 'INTEGER', 'description' => 'Goal ID'],
                    'title' => ['type' => 'STRING', 'description' => 'Goal title to match (partial match)'],
                    'note' => ['type' => 'STRING', 'description' => 'Progress note to add'],
                ],
                'required' => ['note'],
            ],
        ],
        [
            'name' => 'add_reminder',
            'description' => 'Add a reminder with a message and specific date/time.',
            'parameters' => [
                'type' => 'OBJECT',
                'properties' => [
                    'message' => ['type' => 'STRING', 'description' => 'Reminder message'],
                    'remind_at' => ['type' => 'STRING', 'description' => 'Date/time in YYYY-MM-DD HH:MM format'],
                ],
                'required' => ['message', 'remind_at'],
            ],
        ],
        [
            'name' => 'list_upcoming_reminders',
            'description' => 'List upcoming reminders that haven\'t been sent yet.',
        ],
        [
            'name' => 'check_inbox',
            'description' => 'Check recent unread emails from Gmail inbox.',
        ],
        [
            'name' => 'search_emails',
            'description' => 'Search Gmail inbox with a query (e.g. "from:client subject:logo").',
            'parameters' => [
                'type' => 'OBJECT',
                'properties' => [
                    'query' => ['type' => 'STRING', 'description' => 'Gmail search query'],
                ],
                'required' => ['query'],
            ],
        ],
        [
            'name' => 'summarize_email',
            'description' => 'Get the full content/summary of a specific email by ID.',
            'parameters' => [
                'type' => 'OBJECT',
                'properties' => [
                    'email_id' => ['type' => 'STRING', 'description' => 'Gmail message ID'],
                ],
                'required' => ['email_id'],
            ],
        ],
    ],
];

// Call Gemini API
function call_gemini($contents, $system_instruction, $tools = null, $tool_response = null) {
    $body = [
        'contents' => $contents,
        'systemInstruction' => $system_instruction,
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 2048,
        ],
    ];

    if ($tools) {
        $body['tools'] = $tools;
    }

    if ($tool_response) {
        $body['toolConfig'] = [
            'functionCallingConfig' => [
                'mode' => 'NONE',
            ],
        ];
    }

    $ch = curl_init(GEMINI_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_TIMEOUT => 30,
    ]);
    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        error_log('Gemini curl error: ' . $curlError);
        return ['error' => ['message' => 'Curl failed: ' . $curlError]];
    }

    $response = json_decode($raw, true);

    if ($httpCode !== 200) {
        $errMsg = $response['error']['message'] ?? 'HTTP ' . $httpCode;
        error_log('Gemini API error (' . $httpCode . '): ' . $errMsg);
        return ['error' => ['message' => $errMsg]];
    }

    return $response;
}

// Execute tool calls
function execute_tool($name, $args) {
    $db = get_db();
    switch ($name) {
        case 'add_task':
            $title = $args['title'] ?? '';
            $priority = $args['priority'] ?? 'medium';
            $due_date = !empty($args['due_date']) ? $args['due_date'] : null;
            $stmt = $db->prepare("INSERT INTO tasks (title, priority, due_date) VALUES (?, ?, ?)");
            $stmt->execute([$title, $priority, $due_date]);
            $id = $db->lastInsertId();
            return "Task #$id added: \"$title\" ($priority priority" . ($due_date ? ", due $due_date" : "") . ")";

        case 'list_tasks':
            $priority_order = "CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END";
            $stmt = $db->query("SELECT id, title, priority, due_date FROM tasks WHERE status = 'open' ORDER BY $priority_order, due_date ASC");
            $tasks = $stmt->fetchAll();
            if (empty($tasks)) return "No open tasks.";
            $output = "Open tasks:\n";
            foreach ($tasks as $t) {
                $output .= sprintf("#%d — %s [%s]%s\n", $t['id'], $t['title'], $t['priority'], $t['due_date'] ? " (due {$t['due_date']})" : "");
            }
            return trim($output);

        case 'complete_task':
            if (!empty($args['task_id'])) {
                $stmt = $db->prepare("UPDATE tasks SET status = 'done' WHERE id = ?");
                $stmt->execute([$args['task_id']]);
                $count = $stmt->rowCount();
                return $count > 0 ? "Task #{$args['task_id']} marked as done." : "Task not found.";
            }
            if (!empty($args['title'])) {
                $stmt = $db->prepare("UPDATE tasks SET status = 'done' WHERE title LIKE ? AND status = 'open' LIMIT 1");
                $stmt->execute(['%' . $args['title'] . '%']);
                $count = $stmt->rowCount();
                return $count > 0 ? "Task marked as done." : "No matching open task found.";
            }
            return "Please specify a task ID or title.";

        case 'add_goal':
            $title = $args['title'] ?? '';
            $target_date = !empty($args['target_date']) ? $args['target_date'] : null;
            $stmt = $db->prepare("INSERT INTO goals (title, target_date) VALUES (?, ?)");
            $stmt->execute([$title, $target_date]);
            $id = $db->lastInsertId();
            return "Goal #$id added: \"$title\"" . ($target_date ? " (target: $target_date)" : "");

        case 'list_goals':
            $stmt = $db->query("SELECT id, title, target_date, progress_notes, status FROM goals WHERE status = 'active' ORDER BY created_at ASC");
            $goals = $stmt->fetchAll();
            if (empty($goals)) return "No active goals.";
            $output = "Active goals:\n";
            foreach ($goals as $g) {
                $output .= sprintf("#%d — %s%s\n", $g['id'], $g['title'], $g['target_date'] ? " (target: {$g['target_date']})" : "");
                if (!empty($g['progress_notes'])) {
                    $output .= "   Latest: " . mb_substr(explode("\n", trim($g['progress_notes']))[0], 0, 100) . "\n";
                }
            }
            return trim($output);

        case 'update_goal_progress':
            $note = $args['note'] ?? '';
            $goal = null;
            if (!empty($args['goal_id'])) {
                $stmt = $db->prepare("SELECT * FROM goals WHERE id = ? AND status = 'active'");
                $stmt->execute([$args['goal_id']]);
                $goal = $stmt->fetch();
            } elseif (!empty($args['title'])) {
                $stmt = $db->prepare("SELECT * FROM goals WHERE title LIKE ? AND status = 'active' LIMIT 1");
                $stmt->execute(['%' . $args['title'] . '%']);
                $goal = $stmt->fetch();
            } else {
                // Try to find the most recent active goal
                $stmt = $db->query("SELECT * FROM goals WHERE status = 'active' ORDER BY created_at DESC LIMIT 1");
                $goal = $stmt->fetch();
            }
            if (!$goal) return "No matching active goal found.";
            $timestamp = date('Y-m-d H:i');
            $entry = "[$timestamp] $note";
            $existing = $goal['progress_notes'] ? $goal['progress_notes'] . "\n" : '';
            $stmt = $db->prepare("UPDATE goals SET progress_notes = ? WHERE id = ?");
            $stmt->execute([$existing . $entry, $goal['id']]);
            return "Progress note added to goal \"{$goal['title']}\": $note";

        case 'add_reminder':
            $message = $args['message'] ?? '';
            $remind_at = $args['remind_at'] ?? '';
            if (empty($message) || empty($remind_at)) return "Both message and date/time are required.";
            $stmt = $db->prepare("INSERT INTO reminders (message, remind_at) VALUES (?, ?)");
            $stmt->execute([$message, $remind_at]);
            $id = $db->lastInsertId();
            return "Reminder #$id set for $remind_at: \"$message\"";

        case 'list_upcoming_reminders':
            $stmt = $db->query("SELECT id, message, remind_at FROM reminders WHERE sent = 0 ORDER BY remind_at ASC");
            $reminders = $stmt->fetchAll();
            if (empty($reminders)) return "No upcoming reminders.";
            $output = "Upcoming reminders:\n";
            foreach ($reminders as $r) {
                $output .= sprintf("#%d — %s (at %s)\n", $r['id'], $r['message'], $r['remind_at']);
            }
            return trim($output);

        case 'check_inbox':
            if (!gmail_token_exists()) return "Gmail is not connected. Please set it up first.";
            return gmail_check_inbox();

        case 'search_emails':
            if (!gmail_token_exists()) return "Gmail is not connected. Please set it up first.";
            return gmail_search_emails($args['query'] ?? '');

        case 'summarize_email':
            if (!gmail_token_exists()) return "Gmail is not connected. Please set it up first.";
            return gmail_summarize_email($args['email_id'] ?? '');

        default:
            return "Unknown action: $name";
    }
}

// Multi-turn function calling loop
$max_turns = 5;
for ($turn = 0; $turn < $max_turns; $turn++) {
    $response = call_gemini($contents, $system_instruction, $tools);

    if (isset($response['error'])) {
        $reply = 'API error: ' . ($response['error']['message'] ?? json_encode($response['error']));
        break;
    }

    if (!isset($response['candidates'][0]['content']['parts'])) {
        $reply = 'I had trouble processing that. Could you try again?';
        break;
    }

    $candidate = $response['candidates'][0];
    $parts = $candidate['content']['parts'];

    // Check for function calls
    $function_calls = [];
    foreach ($parts as $part) {
        if (isset($part['functionCall'])) {
            $function_calls[] = $part['functionCall'];
        }
    }

    if (empty($function_calls)) {
        // Pure text response
        $reply = $parts[0]['text'] ?? 'No response generated.';
        break;
    }

    // Execute function calls and build tool response
    $tool_parts = [];
    foreach ($function_calls as $fc) {
        $name = $fc['name'];
        $args = $fc['args'] ?? [];
        $result = execute_tool($name, $args);
        $tool_parts[] = [
            'functionResponse' => [
                'name' => $name,
                'response' => ['result' => $result],
            ],
        ];
    }

    // Add model's function call and tool response to contents
    $contents[] = $candidate['content'];
    $contents[] = [
        'role' => 'user',
        'parts' => $tool_parts,
    ];
}

// Save assistant reply
$stmt = $db->prepare("INSERT INTO chat_history (role, content) VALUES ('assistant', ?)");
$stmt->execute([$reply]);

echo json_encode(['reply' => $reply]);
