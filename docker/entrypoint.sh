#!/bin/sh
set -e

cd /var/www/html

if [ -z "${APP_KEY}" ]; then
    echo "ERROR: APP_KEY is required" >&2
    exit 1
fi

# Railway provides DATABASE_URL, parse it into DB_* for Laravel
if [ -n "${DATABASE_URL}" ] && [ -z "${DB_HOST}" ]; then
    # DATABASE_URL format: postgresql://user:password@host:port/database
    export DB_HOST=$(echo "$DATABASE_URL" | sed -n 's|.*@\([^:]*\):\([0-9]*\)/.*|\1|p')
    export DB_PORT=$(echo "$DATABASE_URL" | sed -n 's|.*@\([^:]*\):\([0-9]*\)/.*|\2|p')
    export DB_DATABASE=$(echo "$DATABASE_URL" | sed -n 's|.*/\([^?]*\).*|\1|p')
    export DB_USERNAME=$(echo "$DATABASE_URL" | sed -n 's|://\([^:]*\):.*|\1|p')
    export DB_PASSWORD=$(echo "$DATABASE_URL" | sed -n 's|://[^:]*:\([^@]*\)@.*|\1|p')
fi

php artisan storage:link >/dev/null 2>&1 || true

php artisan package:discover --ansi || true

php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

php-fpm -D

exec nginx -g 'daemon off;'