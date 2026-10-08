#!/bin/sh
set -e

cd /var/www/html

if [ -z "${APP_KEY}" ]; then
    echo "ERROR: APP_KEY is required" >&2
    exit 1
fi

# Railway provides DATABASE_URL, parse it into DB_* for Laravel
if [ -n "${DATABASE_URL}" ] && [ -z "${DB_HOST}" ]; then
    DB_HOST=$(php -r "echo parse_url('$DATABASE_URL', PHP_URL_HOST);")
    DB_PORT=$(php -r "echo parse_url('$DATABASE_URL', PHP_URL_PORT) ?: '5432';")
    DB_DATABASE=$(php -r "echo ltrim(parse_url('$DATABASE_URL', PHP_URL_PATH), '/');")
    DB_USERNAME=$(php -r "echo parse_url('$DATABASE_URL', PHP_URL_USER);")
    DB_PASSWORD=$(php -r "echo parse_url('$DATABASE_URL', PHP_URL_PASS);")
    export DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
fi

# Role switch: Render passes dockerCommand/startCommand as arguments.
# - WORKER_MODE=1 → queue worker (no migrate, no caches, no servers).
# - With arguments → one-shot (cron): run them and exit.
# - No arguments → web (default): migrate + caches + fpm + nginx.
if [ "${WORKER_MODE}" = "1" ]; then
    exec php artisan queue:work --sleep=3 --tries=3 --max-time=3600
fi

if [ $# -gt 0 ]; then
    exec "$@"
fi

php artisan storage:link >/dev/null 2>&1 || true

php artisan package:discover --ansi || true

php docker/migrate.php

php artisan livewire:publish --assets 2>/dev/null || true

php artisan config:cache
php artisan route:cache
php artisan view:cache

php-fpm -D

exec nginx -g 'daemon off;'