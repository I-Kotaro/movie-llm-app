@extends('layouts.app')

@section('title', 'Movie AI - AI映画提案')

@section('content')
<div class="chat-container">
    <div class="chat-messages">
        <!-- AIからのメッセージ例 -->
        <div class="message ai-message">
            <div class="message-header">
            </div>
            <div class="message-bubble">
                こんにちは！映画コンシェルジュです。<br>
                今の気分や、見たい映画のジャンルを教えてください。<br>
                おすすめの映画をご紹介します！
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
    
    <!-- ボトムへスクロールするボタン -->
    <button id="scrollToBottomBtn" class="scroll-to-bottom-btn" style="display: none;" title="最新のメッセージへ">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg>
    </button>
</div>

<style>
.scroll-to-bottom-btn {
    position: absolute;
    bottom: 100px; /* 入力エリアのさらに上へ */
    right: 40px;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background-color: var(--primary-color, #4da8da);
    color: white;
    border: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 100;
    transition: opacity 0.3s, transform 0.3s;
    opacity: 0;
    transform: translateY(10px);
}
.scroll-to-bottom-btn.show {
    opacity: 1;
    transform: translateY(0);
}
.scroll-to-bottom-btn:hover {
    background-color: var(--secondary-color, #2b7aab);
}
</style>

@endsection
