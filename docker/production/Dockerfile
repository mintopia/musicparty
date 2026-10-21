# Use the FrankenPHP image for building/running composer
FROM ghcr.io/mintopia/php:8.4-alpine-cli AS composer

WORKDIR /app/
ENV HOME=/tmp
RUN mkdir -p /app
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
COPY . /app/
COPY docker/production/production.env /app/.env

RUN composer install \
        --no-dev \
        --no-progress \
        --optimize-autoloader \
        --prefer-dist \
        --ignore-platform-reqs

# Use the node23 image to do an npm install
FROM node:23 AS npm

WORKDIR /app/
ENV HOME=/tmp
RUN mkdir -p /app
COPY --from=composer /app/ /app

RUN npm install --production=false && \
    npm run build

# Now our final image
FROM ghcr.io/mintopia/php:8.4-alpine-frankenphp
LABEL org.opencontainers.image.authors="jess@mintopia.net"

COPY --from=npm /app /app
COPY docker/production/00-entrypoint.sh /docker-entrypoint.d/00-entrypoint.sh
RUN chmod +x /docker-entrypoint.d/00-entrypoint.sh && \
    mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    echo 'memory_limit = 512M' > /usr/local/etc/php/conf.d/memory_limit.ini
