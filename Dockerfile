# syntax=docker/dockerfile:1

# ---- 1. Front-end assets (Vite + Tailwind) ----
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---- 2. PHP dependencies (dev packages kept: the demo seeders use Faker factories) ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --no-scripts --prefer-dist --optimize-autoloader --ignore-platform-reqs

# ---- 3. Runtime image (PHP-FPM) ----
FROM php:8.3-fpm-alpine AS app
WORKDIR /var/www/html

RUN apk add --no-cache libzip-dev oniguruma-dev icu-dev curl \
    && docker-php-ext-install pdo_mysql mbstring zip intl bcmath opcache pcntl

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN composer --version >/dev/null 2>&1 || true \
    && chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD php-fpm -t || exit 1

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# ---- 4. Web server image (nginx) with the public assets baked in ----
FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public

# ---- 5. Single-container image (nginx + PHP-FPM + queue + scheduler) for hosts such as Render (keep this the LAST stage: it is the default build target) ----
FROM app AS render
RUN apk add --no-cache nginx supervisor
COPY docker/render/nginx.conf docker/render/supervisord.conf /etc/heatalert/
COPY docker/render/start.sh /usr/local/bin/render-start.sh
RUN chmod +x /usr/local/bin/render-start.sh && rm -f /etc/nginx/http.d/default.conf
ENV RUN_MIGRATIONS=true RUN_SEEDER=true RUN_DEMO_SEED=true
ENTRYPOINT []
CMD ["render-start.sh"]
