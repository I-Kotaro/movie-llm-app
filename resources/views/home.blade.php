@extends('layouts.app')

@section('title', 'Movie AI - AI映画提案')

@section('content')
<style>
    .movie-poster {
        max-width: 150px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        margin: 10px 0;
        display: block;
    }
    
    .movie-card {
        background: rgba(0, 0, 0, 0.2);
        border-radius: 8px;
        padding: 15px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        margin-top: 10px;
    }
    .movie-card-header {
        display: flex;
        gap: 15px;
    }
    .movie-card-poster {
        width: 80px;
        border-radius: 6px;
        object-fit: cover;
    }
    .movie-card-title {
        font-weight: 700;
        font-size: 1.1em;
        margin: 0 0 5px 0;
        color: #fff;
    }
    .movie-card-meta {
        font-size: 0.85em;
        color: #ccc;
        margin: 0 0 3px 0;
    }
    .movie-card-overview {
        font-size: 0.9em;
        line-height: 1.4;
        color: #ddd;
    }
</style>

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

        // 会話履歴を保持する配列
        let chatHistory = [];

        sendBtn.addEventListener('click', () => {
            const text = chatInput.value.trim();
            if (!text) return;

            // 1. ユーザーのメッセージを画面と履歴に追加
            const userMsg = document.createElement('div');
            userMsg.className = 'message user-message';
            userMsg.innerHTML = `<div class="message-bubble">${text}</div>`;
            messagesContainer.appendChild(userMsg);
            
            // 履歴に追加
            chatHistory.push({ role: 'user', content: text });

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

            // 3. API経由で履歴と一緒にLLMに質問を送信
            fetch('/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ 
                    message: text,
                    history: chatHistory // 履歴も送信する
                })
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
                    // AIの返答も履歴に追加
                    chatHistory.push({ role: 'assistant', content: data.reply });

                    // チャット欄の中に直接カードを描画
                    if (data.movies && data.movies.length > 0) {
                        let cardsHtml = '<div style="margin-top: 15px; display: flex; flex-direction: column; gap: 10px;">';
                        data.movies.forEach(movie => {
                            const posterHtml = movie.poster_url 
                                ? `<img src="${movie.poster_url}" alt="${movie.title}" class="movie-card-poster">`
                                : `<div class="movie-card-poster" style="background:#444;display:flex;align-items:center;justify-content:center;font-size:0.8em;text-align:center;">NO IMAGE</div>`;
                            
                            const releaseDate = movie.release_date !== '不明' ? movie.release_date : '公開日不明';
                            const cast = movie.cast ? movie.cast : '不明';

                            cardsHtml += `
                                <div class="movie-card">
                                    <div class="movie-card-header">
                                        ${posterHtml}
                                        <div>
                                            <h3 class="movie-card-title">${movie.title}</h3>
                                            <p class="movie-card-meta">📅 ${releaseDate}</p>
                                            <p class="movie-card-meta">⭐ ${movie.vote_average}</p>
                                            <p class="movie-card-meta">👥 ${cast}</p>
                                        </div>
                                    </div>
                                    <div class="movie-card-overview">
                                        ${movie.overview.length > 100 ? movie.overview.substring(0, 100) + '...' : movie.overview}
                                    </div>
                                </div>
                            `;
                        });
                        cardsHtml += '</div>';
                        replyText += cardsHtml;
                    }

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
