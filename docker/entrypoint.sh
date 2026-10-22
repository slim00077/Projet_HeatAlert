#!/bin/sh
set -e

cd /var/www/html

# Named volumes are created as root; the app runs as www-data.
chown -R www-data:www-data storage bootstrap/cache

run() {
    su -s /bin/sh www-data -c "$*"
}

# One app key for the whole stack: generated once, kept in the shared storage volume.
# An APP_KEY given through .env.docker always wins.
KEY_FILE=storage/app/.app_key
if [ -z "${APP_KEY:-}" ]; then
    if [ ! -s "$KEY_FILE" ]; then
        # Only the app container generates the key; workers start later and read it.
        if [ "$1" = "php-fpm" ]; then
            php artisan key:generate --show --no-ansi > "$KEY_FILE"
            chown www-data:www-data "$KEY_FILE"
        fi
    fi
    APP_KEY="$(cat "$KEY_FILE")"
    export APP_KEY
fi

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

    # Demo accounts and data, loaded only when the database has no user yet.
    if [ "${RUN_SEEDER:-false}" = "true" ]; then
        run "php artisan db:seed-if-empty"
    fi

    # Deployed demo: refresh the demo data on every start so the site is always ready to test.
    # A failure here must not keep the site down.
    if [ "${RUN_DEMO_SEED:-false}" = "true" ]; then
        run "php artisan db:seed --class=DeployDemoSeeder --force" || echo "Demo seeding failed: the site starts without refreshed demo data."
    fi

    touch /tmp/ready
    exec "$@"
fi

exec su -s /bin/sh www-data -c "exec $*"
