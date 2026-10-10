#!/bin/sh
set -e

PUID="${PUID:-1000}"
PGID="${PGID:-1000}"

if ! getent group "$PGID" >/dev/null; then
    addgroup -g "$PGID" app
fi
GROUP_NAME="$(getent group "$PGID" | cut -d: -f1)"

if ! getent passwd "$PUID" >/dev/null; then
    adduser -D -H -u "$PUID" -G "$GROUP_NAME" -h /tmp -s /bin/sh app
fi

chown -R "$PUID:$PGID" /app/storage /app/bootstrap/cache

export HOME=/tmp
if ! su-exec "$PUID:$PGID" php /app/artisan optimize; then
    echo "entrypoint: 'php artisan optimize' failed; check APP_KEY and the configuration. Exiting." >&2
    exit 1
fi

ROLE="${1:-web}"
[ "$#" -gt 0 ] && shift

case "$ROLE" in
    web)
        set -- php artisan octane:frankenphp --caddyfile=/Caddyfile --host=0.0.0.0 --port=8080 "$@"
        ;;
    horizon)
        set -- php artisan horizon "$@"
        ;;
    scheduler)
        set -- php artisan schedule:work "$@"
        ;;
    reverb)
        set -- php artisan reverb:start --host=0.0.0.0 "$@"
        ;;
    migrate)
        set -- php artisan migrate --force --isolated "$@"
        ;;
    php|composer|sh|bash|id|whoami|env|ls|cat)
        set -- "$ROLE" "$@"
        ;;
    *)
        set -- php artisan "$ROLE" "$@"
        ;;
esac

cd /app
exec su-exec "$PUID:$PGID" "$@"
