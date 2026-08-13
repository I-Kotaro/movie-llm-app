export function initScroll(scrollContainer, scrollToBottomBtn, messagesContainer) {
    if (!scrollContainer || !scrollToBottomBtn || !messagesContainer) return;

    // スクロール状態を監視してボタンの表示/非表示を切り替え
    scrollContainer.addEventListener('scroll', () => {
        const containerScrollHeight = scrollContainer === window ? document.documentElement.scrollHeight : scrollContainer.scrollHeight;
        const containerScrollTop = scrollContainer === window ? window.scrollY : scrollContainer.scrollTop;
        const containerClientHeight = scrollContainer === window ? window.innerHeight : scrollContainer.clientHeight;
        
        // ボトムから100px以上離れているか判定
        const isScrolledUp = containerScrollHeight - containerScrollTop - containerClientHeight > 100;
        if (isScrolledUp) {
            scrollToBottomBtn.style.display = 'flex';
            setTimeout(() => scrollToBottomBtn.classList.add('show'), 10);
        } else {
            scrollToBottomBtn.classList.remove('show');
            setTimeout(() => {
                if (!scrollToBottomBtn.classList.contains('show')) {
                    scrollToBottomBtn.style.display = 'none';
                }
            }, 300);
        }
    });

    // ボタンクリックでボトムへスムーズスクロール
    scrollToBottomBtn.addEventListener('click', (e) => {
        e.preventDefault();
        scrollToBottom(messagesContainer);
    });
}

// 外部から呼べるスクロール処理
export function scrollToBottom(messagesContainer) {
    const scrollContainer = document.querySelector('.chat-content') || window;
    const targetHeight = scrollContainer === window ? document.documentElement.scrollHeight : scrollContainer.scrollHeight;
    scrollContainer.scrollTo({
        top: targetHeight,
        behavior: 'smooth'
    });
}
