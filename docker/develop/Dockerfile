FROM dunglas/frankenphp:php8.5 AS base

COPY --from=ghcr.io/php/pie:bin /pie /usr/local/bin/pie

RUN apt-get update && \
    apt-get install -y --no-install-recommends git unzip && \
    rm -rf /var/lib/apt/lists/* && \
    install-php-extensions bcmath pcntl pdo_mysql redis protobuf opentelemetry && \
    pie install pie-extensions/grpc:1.81.1

FROM base

RUN apt-get update && \
    apt-get install -y --no-install-recommends nodejs npm && \
    rm -rf /var/lib/apt/lists/* && \
    npm install -g chokidar && \
    install-php-extensions xdebug && \
    echo 'memory_limit = 512M' > /usr/local/etc/php/conf.d/memory_limit.ini

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/

COPY docker/Caddyfile /
ENV XDEBUG_MODE=off

ENTRYPOINT ["php", "artisan", "octane:frankenphp", "--watch", "--poll", "--admin-host=0.0.0.0", "--port=80", "--caddyfile=/Caddyfile"]
