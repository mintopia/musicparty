#!/bin/sh
set -e

APP_USER=musicparty

if [ "$(id -u)" = "0" ]; then
    PUID="${PUID:-1000}"
    PGID="${PGID:-1000}"

    if [ "$(id -g "$APP_USER")" != "$PGID" ]; then
        groupmod -o -g "$PGID" "$APP_USER"
    fi
    if [ "$(id -u "$APP_USER")" != "$PUID" ]; then
        usermod -o -u "$PUID" -g "$PGID" "$APP_USER"
    fi

    for dir in /data /config "/home/$APP_USER" /app/storage /app/bootstrap/cache; do
        if [ -d "$dir" ]; then
            find "$dir" \( ! -uid "$PUID" -o ! -gid "$PGID" \) -exec chown -h "$PUID:$PGID" {} +
        fi
    done

    export HOME="/home/$APP_USER"
    exec setpriv --reuid="$APP_USER" --regid="$APP_USER" --init-groups --no-new-privs "$0" "$@"
fi

if [ "${ENTRYPOINT_OPTIMIZE:-0}" = "1" ]; then
    php /app/artisan optimize
fi

exec "$@"
