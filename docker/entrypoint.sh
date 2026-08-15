#!/bin/sh
set -e

# RenderのPORT環境変数にあわせてNginxのポートを変更
PORT=${PORT:-80}
sed -i "s/listen 80;/listen $PORT;/g" /etc/nginx/nginx.conf
sed -i "s/listen \[::\]:80;/listen [::]:$PORT;/g" /etc/nginx/nginx.conf

# 最適化キャッシュ
php artisan config:clear || true
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

exec "$@"
