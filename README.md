# AI感情分析型 映画提案アプリケーション (ポートフォリオ)

Laravel 11 と Alpine.js を使用し、
**AIを活用した映画提案Webアプリ**を開発しました。<br>
曖昧な気分や感情をチャットに入力するだけで、Groq API(LLM)とTMDB APIを連携し、今すぐ観たくなる映画を3つ提案します。<br>
「作品数が多くて選べない」という選択のパラドックスを解決し、迷わず映画を決められることを目的としてロジック設計を行っています。<br>

## デモサイト

（※本番デプロイ済みの場合はここにURLを記載）
https://movie-llm-app-demo.example.com/

<!-- DB連携後コメントアウト解除

### 動作確認方法

**ログイン方法**
- Email: test@example.com
- Password: password123
-->


## 使用技術

### フロントエンド
- Alpine.js
- Tailwind CSS
- Blade

### バックエンド
- Laravel 11
- PHP

### データベース
- MySQL 8.0
- PostgreSQL (Supabase)

### その他（外部API等）
- Groq API（AI自然言語解析: `llama-3.3-70b-versatile`）
- TMDB API（映画・配信サービス情報取得）

## 主な機能

- LINE風チャット対話UI
- AI条件解析・検索意図抽出機能
- 映画提案、あらすじ、AIおすすめ理由の表示
- 国内VOD配信情報（Netflix, Amazon Prime等）の表示
- レスポンシブ対応
<!--
- ユーザー認証機能（Laravel Breeze）
- お気に入り（マイリスト）追加・解除機能
-->

<!--
## CRUD機能実装

- Create： アカウント作成、チャットメッセージ送信、お気に入り映画の保存
- Read： 映画提案結果の表示、お気に入り一覧表示
- Update： プロフィール情報編集（Laravel Breeze標準機能）
- Delete： アカウント削除、お気に入り解除

## ER図
-->