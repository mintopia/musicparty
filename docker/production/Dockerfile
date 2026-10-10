FROM ghcr.io/mintopia/php-frankenphp-base:8.4.10 AS composer

WORKDIR /app/
ENV HOME=/tmp
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock /app/

RUN composer install \
        --no-dev \
        --no-progress \
        --no-scripts \
        --no-autoloader \
        --prefer-dist

COPY . /app/
COPY docker/production/production.env /app/.env

RUN composer dump-autoload --no-dev --optimize && \
    php artisan package:discover --ansi

FROM node:24 AS npm

WORKDIR /app/
ENV HOME=/tmp
COPY package.json package-lock.json /app/

RUN npm ci

COPY vite.config.js /app/
COPY resources /app/resources
COPY public /app/public

RUN npm run build

FROM ghcr.io/mintopia/php-frankenphp-base:8.4.10
LABEL org.opencontainers.image.authors="jess@mintopia.net"

RUN apk add --no-cache su-exec && \
    mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    echo 'memory_limit = 512M' > /usr/local/etc/php/conf.d/memory_limit.ini

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/Caddyfile /
COPY docker/production/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

COPY --from=composer /app /app
COPY --from=npm /app/public/build /app/public/build

WORKDIR /app
EXPOSE 8080
ENTRYPOINT ["/entrypoint.sh"]
CMD ["web"]
