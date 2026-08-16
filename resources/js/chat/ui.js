import { sendMessageToLLM } from './api';
import { initScroll, scrollToBottom } from './scroll';

export function initChatUI() {
    const sendBtn = document.getElementById('sendBtn');
    const chatInput = document.getElementById('chatInput');
    const messagesContainer = document.querySelector('.chat-messages');
    const filmLayout = document.querySelector('.film-layout');
    const scrollToBottomBtn = document.getElementById('scrollToBottomBtn');
    const scrollContainer = document.querySelector('.chat-content') || window;

    if (!sendBtn || !chatInput || !messagesContainer || !filmLayout) return;

    let chatHistory = [];

    // スクロール処理の初期化
    initScroll(scrollContainer, scrollToBottomBtn, messagesContainer);

    // メッセージ送信処理
    const handleSend = () => {
        const text = chatInput.value.trim();
        if (!text) return;

        // 1. ユーザーのメッセージを追加
        const userMsg = document.createElement('div');
        userMsg.className = 'message user-message';
        userMsg.innerHTML = `<div class="message-bubble">${text}</div>`;
        messagesContainer.appendChild(userMsg);
        
        chatHistory.push({ role: 'user', content: text });
        chatInput.value = '';
        scrollToBottom(messagesContainer);

        // 2. アニメーション開始＆タイピングウェーブ表示
        filmLayout.classList.add('is-loading');

        const typingMsg = document.createElement('div');
        typingMsg.className = 'message ai-message';
        typingMsg.innerHTML = `
            <div class="message-bubble">
                <div class="typing-dots">
                    <span></span><span></span><span></span>
                </div>
            </div>
        `;
        messagesContainer.appendChild(typingMsg);
        scrollToBottom(messagesContainer);

        // 3. API経由で履歴と一緒にLLMに質問を送信
        sendMessageToLLM(text, chatHistory)
            .then(data => {
                messagesContainer.removeChild(typingMsg);

                const aiMsg = document.createElement('div');
                aiMsg.className = 'message ai-message';
                
                let replyText = 'エラーが発生しました。';
                if (data.status === 'success') {
                    replyText = data.reply.replace(/\n/g, '<br>');
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
                                    <div class="movie-card-overview" style="margin-bottom: 10px;">
                                        <strong>【あらすじ】</strong><br>
                                        ${movie.overview ? movie.overview : '<span style="color: #888;">（TMDBにあらすじ情報が登録されていません）</span>'}
                                    </div>
                                    <div class="movie-providers" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #333;">
                                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                                            <div>
                                                ${movie.providers && movie.providers.length > 0 ? `
                                                <strong style="display: block; margin-bottom: 5px;">視聴可能な配信サービス:</strong>
                                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                                    ${movie.providers.map(p => `
                                                        <img src="${p.logo_url}" alt="${p.provider_name}" title="${p.provider_name}" style="width: 32px; height: 32px; border-radius: 8px; object-fit: cover;">
                                                    `).join('')}
                                                </div>
                                                ` : '<span style="color: #888; font-size: 0.9rem;">現在配信・レンタル・購入情報はありません</span>'}
                                            </div>
                                            
                                            <a href="${movie.provider_link ? movie.provider_link : `https://www.themoviedb.org/movie/${movie.id}?language=ja-JP`}" target="_blank" style="color: #4da8da; text-decoration: none; font-size: 0.9rem; border: 1px solid #4da8da; padding: 4px 10px; border-radius: 20px; white-space: nowrap; transition: all 0.2s;" onmouseover="this.style.background='#4da8da'; this.style.color='#fff';" onmouseout="this.style.background='transparent'; this.style.color='#4da8da';">
                                                詳細はこちら ↗
                                            </a>
                                        </div>
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
                    <div class="message-bubble">
                        ${replyText}
                    </div>
                `;
                messagesContainer.appendChild(aiMsg);
            })
            .catch(error => {
                console.error('Error:', error);
                if (messagesContainer.contains(typingMsg)) {
                    messagesContainer.removeChild(typingMsg);
                }
                const aiMsg = document.createElement('div');
                aiMsg.className = 'message ai-message';
                aiMsg.innerHTML = `
                    <div class="message-bubble">
                        通信エラーが発生しました。<br>
                        ネットワークの切断、または短時間の連続送信による制限の可能性があります。<br>
                        少し時間をあけてから再度お試しください。
                    </div>
                `;
                messagesContainer.appendChild(aiMsg);
            })
            .finally(() => {
                // 4. アニメーション停止
                filmLayout.classList.remove('is-loading');
            });
    };

    sendBtn.addEventListener('click', handleSend);

    chatInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            if (e.isComposing) return;
            e.preventDefault();
            handleSend();
        }
    });
}
