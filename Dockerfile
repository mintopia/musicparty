FROM 1and1internet/php-build-environment:8.1 AS build
LABEL maintainer="jess@mintopia.net"

WORKDIR /app/
USER 1000
ENV HOME /tmp
COPY --chown=1000:1000 . /app/

RUN composer install \
        --no-dev \
        --no-progress \
        --optimize-autoloader \
        --prefer-dist

FROM php:8.1-fpm-alpine
LABEL maintainer="jess@mintopia.net"

RUN apk update \
    && apk --no-cache add \
        ${PHPIZE_DEPS} \
        freetype-dev\
        libpng-dev \
        libjpeg-turbo-dev \
        nginx \
    # Remove (some of the) default nginx config
    && rm -f /etc/nginx.conf /etc/nginx/conf.d/default.conf \
    && rm -rf /etc/nginx/sites-* \
    # Ensure nginx logs, even if the config has errors, are written to stderr
    && ln -s /dev/stderr /var/lib/nginx/logs/error.log \
    && chmod -R 777 /var/log/nginx /var/lib/nginx \
    && pecl install -o -f redis \
    && rm -rf /tmp/pear \
    && docker-php-ext-install \
        bcmath \
        pdo_mysql \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install gd \
    && docker-php-ext-enable redis \
    && apk del --no-cache ${PHPIZE_DEPS} \
    && rm -vrf /tmp/pear /var/cache/apk/* \
    && mkdir -p /var/www /tmp \
    && chown -R 1000:1000 /var/www /tmp

COPY files /
COPY --from=build --chown=1000:1000 /app/ /var/www/

# Install s6 overlay for service management
ADD https://github.com/just-containers/s6-overlay/releases/download/v2.1.0.2/s6-overlay-amd64.tar.gz /tmp/
RUN \
    gunzip -c /tmp/s6-overlay-amd64.tar.gz | tar -xf - -C / \
    && mkdir -p /etc/services.d \
    # Support running s6 under a non-root user
    && chown -R 1000:1000 /etc/services.d /etc/s6 /run /var/lib/nginx \
    && chmod -R 777 /etc/services.d

WORKDIR /var/www/
USER 1000
ENV HOME /tmp

EXPOSE 8000

ENTRYPOINT ["/init"]
