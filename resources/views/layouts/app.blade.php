<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Movie AI')</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Outfit:wght@500;700;900&display=swap" rel="stylesheet">
    
    <!-- Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- テーマ初期化スクリプト (チラつき防止) -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('app-theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>
<body>
    <!-- サイドバー用のオーバーレイ -->
    <div id="sidebarOverlay" class="sidebar-overlay"></div>

    <!-- スライド式サイドバー -->
    <aside id="appSidebar" class="app-sidebar">
        <div class="sidebar-header">
            <h2>Menu</h2>
            <button id="closeSidebarBtn" class="icon-btn" aria-label="閉じる">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        <div class="sidebar-content">
            <div class="sidebar-item">
                <span>ダークモード</span>
                <label class="theme-switch" for="themeToggleCheckbox">
                    <input type="checkbox" id="themeToggleCheckbox">
                    <span class="slider"></span>
                </label>
            </div>
            <!-- 今後ここに「履歴一覧」などを追加 -->
        </div>
    </aside>

    <div class="film-layout">
        <div class="app-container chat-layout">
            <header class="glass-header">
                <div class="glass-header-inner">
                    <!-- ハンバーガーメニュー -->
                    <button id="openSidebarBtn" class="icon-btn" aria-label="メニューを開く">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                    </button>
                    <div class="logo">映画コンシェルジュ</div>
                    <div style="width: 24px;"></div> <!-- レイアウトのバランス調整用 -->
                </div>
            </header>
            
            <main class="main-content chat-content">
                @yield('content')
            </main>
        </div>
    </div>

</body>
</html>