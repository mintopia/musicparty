FROM ghcr.io/mintopia/php-frankenphp-base:8.4.10

RUN apk add --update nodejs npm && \
    npm install -g chokidar && \
    install-php-extensions xdebug && \
    echo 'memory_limit = 512M' > /usr/local/etc/php/conf.d/memory_limit.ini

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/

COPY docker/Caddyfile /
ENV XDEBUG_MODE=off

ENTRYPOINT ["php", "artisan", "octane:frankenphp", "--watch", "--poll", "--admin-host=0.0.0.0", "--port=80", "--caddyfile=/Caddyfile"]
