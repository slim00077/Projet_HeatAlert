#!/bin/sh
set -e

cd /var/www/html

# Named volumes are created as root; the app runs as www-data.
chown -R www-data:www-data storage bootstrap/cache

run() {
    su -s /bin/sh www-data -c "$*"
}

# Only the web container prepares the application; workers just start (as www-data).
if [ "$1" = "php-fpm" ]; then
    rm -f /tmp/ready
    run "php artisan storage:link --force" >/dev/null 2>&1 || true
    run "php artisan config:cache"
    run "php artisan route:cache"
    run "php artisan view:cache"

    if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
        run "php artisan migrate --force"
    fi

    touch /tmp/ready
    exec "$@"
fi

exec su -s /bin/sh www-data -c "exec $*"
