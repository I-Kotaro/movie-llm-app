@extends('layouts.app')

@section('title', 'Movie AI - AI映画提案')

@section('content')
<div class="chat-container">
    <div class="chat-messages">
        <!-- AIからのメッセージ例 -->
        <div class="message ai-message">
            <div class="message-avatar">🎬</div>
            <div class="message-bubble">
                こんにちは！映画コンシェルジュです。<br>
                今の気分や、見たい映画のジャンルを教えてください。<br>
            </div>
        </div>
    </div>
    
    <!-- 固定の入力エリア -->
    <div class="chat-input-area">
        <input type="text" id="chatInput" class="chat-input" placeholder="元気が出るアクション映画ある？">
        <button id="sendBtn" class="chat-send-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
        </button>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const sendBtn = document.getElementById('sendBtn');
        const chatInput = document.getElementById('chatInput');
        const messagesContainer = document.querySelector('.chat-messages');
        const filmLayout = document.querySelector('.film-layout'); // アニメーションを制御する親要素

        sendBtn.addEventListener('click', () => {
            const text = chatInput.value.trim();
            if (!text) return;

            // 1. ユーザーのメッセージを画面に追加
            const userMsg = document.createElement('div');
            userMsg.className = 'message user-message';
            userMsg.innerHTML = `<div class="message-bubble">${text}</div>`;
            messagesContainer.appendChild(userMsg);

            // 入力欄をクリアして一番下までスクロール
            chatInput.value = '';
            messagesContainer.scrollTop = messagesContainer.scrollHeight;

            // 2. フィルムアニメーション開始（AI思考中）＆タイピングウェーブ表示
            filmLayout.classList.add('is-loading');

            const typingMsg = document.createElement('div');
            typingMsg.className = 'message ai-message';
            typingMsg.innerHTML = `
                <div class="message-avatar">🎬</div>
                <div class="message-bubble">
                    <div class="typing-dots">
                        <span></span><span></span><span></span>
                    </div>
                </div>
            `;
            messagesContainer.appendChild(typingMsg);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;

            // 3. API経由でLLMに質問を送信
            fetch('/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ message: text })
            })
            .then(response => response.json())
            .then(data => {
                // 考え中のウェーブ（3つの点）を削除
                messagesContainer.removeChild(typingMsg);

                const aiMsg = document.createElement('div');
                aiMsg.className = 'message ai-message';
                
                let replyText = 'エラーが発生しました。';
                if (data.status === 'success') {
                    replyText = data.reply.replace(/\n/g, '<br>');
                } else if (data.message) {
                    replyText = data.message;
                }

                aiMsg.innerHTML = `
                    <div class="message-avatar">🎬</div>
                    <div class="message-bubble">
                        ${replyText}
                    </div>
                `;
                messagesContainer.appendChild(aiMsg);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            })
            .catch(error => {
                console.error('Error:', error);
                messagesContainer.removeChild(typingMsg);
                const aiMsg = document.createElement('div');
                aiMsg.className = 'message ai-message';
                aiMsg.innerHTML = `
                    <div class="message-avatar">🎬</div>
                    <div class="message-bubble">
                        通信エラーが発生しました。
                    </div>
                `;
                messagesContainer.appendChild(aiMsg);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            })
            .finally(() => {
                // 4. アニメーション停止
                filmLayout.classList.remove('is-loading');
            });
        });

        // Enterキーでも送信できるようにする
        chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendBtn.click();
            }
        });
    });
</script>
@endsection
