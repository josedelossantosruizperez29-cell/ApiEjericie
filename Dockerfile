FROM php:8.3-apache-bookworm AS runtime
WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install -j2 pdo_pgsql pgsql opcache \
    && apt-mark manual libpq5 \
    && apt-get purge -y --auto-remove libpq-dev \
    && rm -rf /var/lib/apt/lists/* \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-fit-home.ini

FROM runtime AS build
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
RUN apt-get update && apt-get install -y --no-install-recommends unzip
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader
COPY app/ app/
COPY database/ database/
COPY bootstrap/ bootstrap/
COPY config/ config/
COPY resources/ resources/
COPY routes/ routes/
COPY public/ public/
COPY artisan .
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
    && composer check-platform-reqs --no-dev \
    && php artisan package:discover

FROM runtime
ENV APP_ENV=production APP_DEBUG=false APP_NAME="Fit Home" \
    DB_CONNECTION=pgsql DB_SSLMODE=require SESSION_DRIVER=database \
    SESSION_SECURE_COOKIE=true CACHE_STORE=database QUEUE_CONNECTION=sync \
    LOG_CHANNEL=stderr LOG_LEVEL=warning
COPY --from=build /var/www/html/ ./
COPY docker/apache.conf /etc/apache2/fit-home.conf
COPY --chmod=755 docker/start.sh /usr/local/bin/fit-home-start
RUN chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 10000
STOPSIGNAL SIGWINCH
ENTRYPOINT ["fit-home-start"]
