# 1. フロントエンドのビルドステージ (Vite)
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# 2. PHP依存関係のインストールステージ (Composer)
FROM composer:2 AS composer
WORKDIR /app
COPY composer*.json ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --optimize --no-dev

# 3. 本番実行ステージ (FrankenPHP)
FROM dunglas/frankenphp:1-php8.3-alpine

# 必要なPHP拡張をインストール
RUN install-php-extensions \
    pcntl \
    bcmath \
    intl \
    opcache \
    zip \
    pdo_mysql

ENV SERVER_NAME="http://"
ENV APP_ENV="production"
ENV APP_DEBUG="false"

WORKDIR /app

# アプリケーションコードとビルド済みアセットを配置
COPY . .
COPY --from=composer /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build

# 設定ファイル・起動スクリプトのコピー
COPY ./docker/Caddyfile /etc/caddy/Caddyfile
COPY ./docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# パーミッション設定
RUN chmod -R 777 storage bootstrap/cache

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
