FROM --platform=$BUILDPLATFORM ghcr.io/mintopia/php:8.5-cli AS composer

WORKDIR /app/
ENV HOME=/tmp
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
RUN apk add --no-cache git unzip
COPY . /app/
COPY docker/production/production.env /app/.env

RUN REVERB_APP_ID=build REVERB_APP_KEY=build REVERB_APP_SECRET=build composer install \
        --no-dev \
        --no-progress \
        --optimize-autoloader \
        --prefer-dist

# PHP is needed because the Wayfinder Vite plugin runs artisan.
FROM --platform=$BUILDPLATFORM ghcr.io/mintopia/php:8.5-cli AS npm

RUN apk add --no-cache nodejs npm
WORKDIR /app/
ENV HOME=/tmp
COPY --from=composer /app/ /app

RUN npm ci --include=dev && \
    REVERB_APP_ID=build REVERB_APP_KEY=build REVERB_APP_SECRET=build npm run build

# Wayfinder must emit relative URLs; an absolute host here means the build-time
# APP_URL leaked into the bundle and every route would point at it at runtime.
RUN test -d resources/js/routes && ! grep -rIE 'url: ?.https?://localhost' resources/js/routes public/build

FROM ghcr.io/mintopia/php:8.5-frankenphp
LABEL org.opencontainers.image.authors="jess@mintopia.net"

COPY --from=npm /app /app
COPY docker/hooks/05-reverb-proxy docker/hooks/30-mods-sync /docker-entrypoint.d/

ENV PUID=1000 \
    PGID=1000 \
    LARAVEL_OCTANE=true \
    LARAVEL_OPTIMIZE=true \
    PHP_MEMORY_LIMIT=512M \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0
