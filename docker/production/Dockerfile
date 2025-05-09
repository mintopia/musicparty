# Use the FrankenPHP image for building/running composer
FROM dunglas/frankenphp:1.5.0-php8.4.5-alpine AS composer

WORKDIR /app/
ENV HOME=/tmp
RUN mkdir -p /app && \
    install-php-extensions \
        pcntl \
        redis
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
COPY . /app/
COPY docker/production/production.env /app/.env

RUN composer install \
        --no-dev \
        --no-progress \
        --optimize-autoloader \
        --prefer-dist

# Use the node23 image to do an npm install
FROM node:23 AS npm

WORKDIR /app/
ENV HOME=/tmp
RUN mkdir -p /app
COPY --from=composer /app/ /app

RUN npm install --production=false && \
    npm run build

# Now our final image
FROM dunglas/frankenphp:1.5.0-php8.4.5-alpine
LABEL org.opencontainers.image.authors="jess@mintopia.net"

COPY --from=npm /app /app
COPY docker/production/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

COPY docker/Caddyfile /

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php",  "artisan",  "octane:frankenphp", "--caddyfile=/Caddyfile", "--admin-host=0.0.0.0", "--port=80"]

RUN install-php-extensions \
    pcntl \
    redis \
    bcmath \
    pdo \
    pdo_mysql && \
    mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    echo 'memory_limit = 256M' > /usr/local/etc/php/conf.d/memory_limit.ini
