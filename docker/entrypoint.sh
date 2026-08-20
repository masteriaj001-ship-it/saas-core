#!/bin/sh
set -e

cd /var/www/html

if [ -z "${APP_KEY}" ]; then
    echo "ERROR: APP_KEY is required" >&2
    exit 1
fi

php artisan storage:link >/dev/null 2>&1 || true

php artisan package:discover --ansi || true

php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

php-fpm -D

exec nginx -g 'daemon off;'