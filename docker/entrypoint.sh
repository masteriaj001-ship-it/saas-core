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

php artisan storage:link >/dev/null 2>&1 || true

php artisan package:discover --ansi || true

php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

php-fpm -D

exec nginx -g 'daemon off;'