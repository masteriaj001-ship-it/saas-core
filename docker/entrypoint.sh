#!/bin/sh
set -e

cd /var/www/html

if [ -z "${APP_KEY}" ]; then
    echo "ERROR: APP_KEY is required" >&2
    exit 1
fi

# Railway PostgreSQL: map PG* vars to DB_* if not already set
if [ -n "${PGHOST}" ] && [ -z "${DB_HOST}" ]; then
    export DB_HOST="${PGHOST}"
    export DB_PORT="${PGPORT:-5432}"
    export DB_DATABASE="${PGDATABASE}"
    export DB_USERNAME="${PGUSER}"
    export DB_PASSWORD="${PGPASSWORD}"
fi

php artisan storage:link >/dev/null 2>&1 || true

php artisan package:discover --ansi || true

php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

php-fpm -D

exec nginx -g 'daemon off;'