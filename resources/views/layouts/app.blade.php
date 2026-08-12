<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Movie AI')</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Outfit:wght@500;700;900&display=swap" rel="stylesheet">
    
    <!-- Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="film-layout">
        <!-- 中央のチャットアプリコンテナ（これがフィルム本体になります） -->
        <div class="app-container chat-layout">
            <header class="glass-header">
                <div class="logo">仮ヘッダー</div>
            </header>
            
            <main class="main-content chat-content">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>