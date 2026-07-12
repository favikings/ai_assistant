# Personal Virtual Assistant — v1 Build Spec

## 1. What this is
A private, single-user, chat-based virtual assistant, accessed via mobile browser (added to home screen). The owner chats with it in natural language; it manages tasks, goals, reminders, reads their Gmail inbox, and acts as a general-purpose PA for anything conversational (drafting, brainstorming, quick advice).

This is a personal-use tool for the owner only — not a multi-user product.

## 2. Constraints
- **Hosting:** Paid shared hosting already owned (PHP + MySQL available via cPanel). No hosting cost to budget for.
- **AI model:** Google Gemini API — free tier only, no paid usage.
- **Scheduler:** cron-job.org (free) for background reminder checks.
- **Email:** Gmail API via OAuth2 (free, standard Google Cloud quota — no paid tier needed).
- **No other paid services.** Everything outside hosting must be free tier.

## 3. Core features (v1 scope)

### 3.1 Chat interface
- Single continuous conversation thread, mobile-first responsive design.
- Password-protected (single shared password, session-based login — no multi-user accounts needed).
- Chat history persisted in the database so the assistant retains context across sessions.
- Installable to phone home screen (behaves like a lightweight app).

### 3.2 Task management
- Add a task (title, priority: low/medium/high, optional due date).
- List open tasks, sorted by priority then due date.
- Mark a task as done.
- Conversational triggers, e.g.:
  - "Add a task: follow up with the client, high priority"
  - "What's on my plate today?"
  - "Mark the client follow-up as done"

### 3.3 Goals
- Add a goal (title, optional target date).
- List active goals.
- Add a progress note to an existing goal (appends to a running log, doesn't overwrite).
- Conversational triggers, e.g.:
  - "New goal: validate the AI product idea with 15 youth by end of August"
  - "Add progress note to that goal: talked to 5 kids so far"
  - "How are my active goals looking?"

### 3.4 Reminders
- Add a reminder (message + specific date/time).
- List upcoming (unsent) reminders.
- **Pull-based:** assistant can surface due/upcoming reminders when asked or when chat opens.
- **Push-based:** a cron job (via cron-job.org, hitting a dedicated endpoint every 15–30 min) checks for due reminders and emails the owner when one fires. Marks reminder as sent after firing.

### 3.5 Gmail integration (read-focused, v1)
- OAuth2 connection to the owner's Gmail account (one-time authorization, refresh token stored securely on the server).
- Capabilities exposed to the assistant as tools:
  - `check_inbox` — pull recent/unread emails (sender, subject, snippet, date).
  - `search_emails(query)` — natural-language-ish search over the inbox (e.g. "emails from the client about the logo").
  - `summarize_email(id)` — return a short summary of one email's content.
- Conversational triggers, e.g.:
  - "Anything important in my inbox today?"
  - "Any emails from [client] about the logo revisions?"
- **Out of scope for v1:** sending emails on the owner's behalf, replying, archiving/deleting, or any write actions on the mailbox. Read-only.

### 3.6 General PA duties
- Anything conversational that doesn't touch the database or Gmail: drafting messages, brainstorming, rewriting text, quick advice, organizing thoughts into a plan.
- No special handling needed — the assistant answers directly using its own reasoning.

## 4. Data model (MySQL)

```sql
tasks:
  id, title, status (open/done), priority (low/medium/high), due_date, created_at

goals:
  id, title, target_date, progress_notes (text, appended log), status (active/done/paused), created_at

reminders:
  id, message, remind_at (datetime), sent (boolean), created_at

chat_history:
  id, role (user/assistant), content, created_at

gmail_tokens:
  id, access_token, refresh_token, expires_at
```

## 5. How the AI decides to act
Gemini's **function calling** feature is used: the model is given a fixed set of "tools" (add_task, list_tasks, complete_task, add_goal, list_goals, update_goal_progress, add_reminder, list_upcoming_reminders, check_inbox, search_emails, summarize_email). When the owner's message implies one of these actions, the model calls the matching tool instead of just replying in text; the app executes the real PHP function, returns the result to the model, and the model gives a natural-language reply based on it.

## 6. Notification flow (reminders)
```
cron-job.org (every 15-30 min)
   → hits /check_reminders.php on the hosting account
   → finds reminders where remind_at <= now AND sent = 0
   → sends an email to the owner for each
   → marks them as sent
```

## 7. Explicit non-goals for v1
- No sending/replying to emails.
- No writing to Gmail (labels, archive, delete).
- No multi-user support / accounts.
- No native mobile app — browser-based, home-screen-installable only.
- No integrations beyond Gmail (no calendar, no other services) in this version.
- No paid AI usage — must operate within Gemini's free tier limits.

## 8. What "done" looks like for v1
Owner can, from their phone:
1. Log in with a password.
2. Add/view/complete tasks through chat.
3. Add/view goals and log progress notes through chat.
4. Set reminders and receive an email ping when they're due.
5. Ask "what's in my inbox" and get a real, current answer from Gmail.
6. Ask general questions/requests and get a helpful reply, with the conversation remembering earlier context.

## 9. Setup items the build agent will need from the owner
- Hosting cPanel access (DB creation, file upload, cron job setup).
- A free Gemini API key (from Google AI Studio).
- A Google Cloud project with Gmail API enabled + OAuth consent + client credentials.
- A cron-job.org account (free) pointed at the hosting's `check_reminders.php` endpoint.
- A chosen login password for the chat app.
