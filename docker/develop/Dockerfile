FROM dunglas/frankenphp:php8.5 AS base

COPY --from=ghcr.io/php/pie:bin /pie /usr/local/bin/pie

RUN apt-get update && \
    apt-get install -y --no-install-recommends git unzip && \
    rm -rf /var/lib/apt/lists/* && \
    install-php-extensions bcmath pcntl pdo_mysql redis protobuf opentelemetry && \
    pie install pie-extensions/grpc:1.81.1

FROM base

COPY --from=node:24-trixie-slim /usr/local/bin/node /usr/local/bin/
COPY --from=node:24-trixie-slim /usr/local/lib/node_modules /usr/local/lib/node_modules

RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm && \
    ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx && \
    npm install -g chokidar && \
    install-php-extensions xdebug && \
    echo 'memory_limit = 512M' > /usr/local/etc/php/conf.d/memory_limit.ini

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/

COPY docker/Caddyfile /
COPY docker/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh && \
    setcap -r /usr/local/bin/frankenphp && \
    groupadd -g 1000 musicparty && \
    useradd -u 1000 -g 1000 -m -s /bin/sh musicparty && \
    chmod 755 /home/musicparty && \
    mkdir -p /data/caddy /config/caddy && \
    chown -R musicparty:musicparty /data /config && \
    chmod -R a+rwX /data /config

ENV XDEBUG_MODE=off

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php", "artisan", "octane:frankenphp", "--watch", "--poll", "--host=0.0.0.0", "--admin-host=0.0.0.0", "--port=8080", "--caddyfile=/Caddyfile"]
