FROM ghcr.io/mintopia/php:8.5-frankenphp

RUN apk add --no-cache git unzip nodejs npm && \
    npm install -g chokidar && \
    install-php-extensions xdebug && \
    rm -rf /tmp/.npm

COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/

COPY docker/hooks/01-app-owner docker/hooks/05-reverb-proxy /docker-entrypoint.d/

ENV PHP_INI_MODE=development \
    PHP_MEMORY_LIMIT=512M \
    XDEBUG_MODE=off
