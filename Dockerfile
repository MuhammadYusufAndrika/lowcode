FROM php:8.4-fpm-bookworm AS php-base

RUN apt-get update && apt-get install -y --no-install-recommends libicu-dev libzip-dev libonig-dev libcurl4-openssl-dev libxml2-dev libfcgi-bin procps unzip \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring intl zip pcntl bcmath opcache curl dom \
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

WORKDIR /var/www/html

FROM php-base AS dependencies
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY . .
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && composer dump-autoload --no-dev --classmap-authoritative --no-interaction \
    && composer check-platform-reqs --no-dev

FROM node:22-bookworm-slim AS frontend
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY public ./public
COPY vite.config.js tsconfig.json ./
COPY --from=dependencies /var/www/html/vendor ./vendor
RUN npm run build

FROM php-base AS runtime
COPY --from=dependencies --chown=www-data:www-data /var/www/html /var/www/html
COPY --from=frontend --chown=www-data:www-data /build/public/build ./public/build
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-processflow.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-processflow.conf
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/processflow-entrypoint
RUN mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
ENTRYPOINT ["processflow-entrypoint"]
CMD ["php-fpm", "-F"]

FROM nginx:1.28-alpine AS web
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=runtime /var/www/html/public /var/www/html/public
