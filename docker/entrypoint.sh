#!/bin/sh
set -e

cd /var/www/html

# Only the web container prepares the application; workers just start.
if [ "$1" = "php-fpm" ]; then
    php artisan storage:link --force >/dev/null 2>&1 || true
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
        php artisan migrate --force
    fi
fi

exec "$@"
