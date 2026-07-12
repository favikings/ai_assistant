(() => {
    const chatMessages = document.getElementById('chat-messages');
    const chatForm = document.getElementById('chat-form');
    const chatInput = document.getElementById('chat-input');
    const sendBtn = document.getElementById('send-btn');
    const typingIndicator = document.getElementById('typing-indicator');
    const gmailBtn = document.getElementById('gmail-btn');

    let isProcessing = false;

    // Auto-resize textarea
    chatInput.addEventListener('input', () => {
        chatInput.style.height = 'auto';
        chatInput.style.height = Math.min(chatInput.scrollHeight, 120) + 'px';
        sendBtn.disabled = !chatInput.value.trim() || isProcessing;
    });

    // Handle enter/shift+enter
    chatInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (!sendBtn.disabled) chatForm.dispatchEvent(new Event('submit'));
        }
    });

    // Submit message
    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const text = chatInput.value.trim();
        if (!text || isProcessing) return;

        isProcessing = true;
        sendBtn.disabled = true;
        chatInput.value = '';
        chatInput.style.height = 'auto';

        appendMessage('user', text);
        showTyping();

        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: text })
            });

            const data = await res.json();
            hideTyping();

            if (data.error) {
                appendMessage('assistant', 'Something went wrong: ' + data.error);
            } else {
                appendMessage('assistant', data.reply);
            }
        } catch (err) {
            hideTyping();
            appendMessage('assistant', 'Network error. Please try again.');
        }

        isProcessing = false;
        sendBtn.disabled = !chatInput.value.trim();
    });

    // Gmail button
    gmailBtn.addEventListener('click', () => {
        if (!window.APP_CONFIG.gmailConnected) {
            window.location.href = 'setup_gmail.php';
        }
    });

    function appendMessage(role, content) {
        const div = document.createElement('div');
        div.className = `message ${role}-message`;
        const contentDiv = document.createElement('div');
        contentDiv.className = 'message-content';
        contentDiv.textContent = content;
        div.appendChild(contentDiv);
        chatMessages.appendChild(div);
        scrollToBottom();
    }

    function showTyping() {
        typingIndicator.classList.remove('hidden');
        scrollToBottom();
    }

    function hideTyping() {
        typingIndicator.classList.add('hidden');
    }

    function scrollToBottom() {
        requestAnimationFrame(() => {
            document.getElementById('chat-container').scrollTop = document.getElementById('chat-container').scrollHeight;
        });
    }

    // Load chat history on page load
    async function loadHistory() {
        try {
            const res = await fetch('api.php?action=history');
            const data = await res.json();
            if (data.messages && data.messages.length > 0) {
                chatMessages.innerHTML = '';
                data.messages.forEach(msg => {
                    appendMessage(msg.role, msg.content);
                });
            }
        } catch (err) {
            // Silently fail — keep default welcome message
        }
    }

    loadHistory();

    // Register service worker
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js').catch(() => {});
    }
})();
