#!/usr/bin/env bash
# Boots the production image plus a Reverb container and checks ADR 0004 routing.
set -euo pipefail

IMAGE="${IMAGE:-musicparty:smoke}"
SUFFIX="$$"
NETWORK="mp-smoke-${SUFFIX}"
APP="mp-smoke-app-${SUFFIX}"
REVERB="mp-smoke-reverb-${SUFFIX}"
WORK="$(mktemp -d)"

cleanup() {
    docker rm -f "$APP" "$REVERB" >/dev/null 2>&1 || true
    docker network rm "$NETWORK" >/dev/null 2>&1 || true
    rm -rf "$WORK"
}
trap cleanup EXIT

if [ "${BUILD:-1}" = "1" ]; then
    docker build -f docker/production/Dockerfile -t "$IMAGE" .
fi

ENV_ARGS=(
    -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
    -e SESSION_DRIVER=array
    -e CACHE_STORE=array
    -e QUEUE_CONNECTION=sync
    -e BROADCAST_CONNECTION=null
    -e REVERB_UPSTREAM_HOST="$REVERB"
    -e REVERB_UPSTREAM_PORT=8080
)

docker network create "$NETWORK" >/dev/null

docker run -d --name "$REVERB" --network "$NETWORK" "${ENV_ARGS[@]}" "$IMAGE" \
    php artisan reverb:start --host=0.0.0.0 --port=8080 >/dev/null
docker run -d --name "$APP" --network "$NETWORK" "${ENV_ARGS[@]}" "$IMAGE" >/dev/null

# Probe from inside the network so no host port is needed.
probe() {
    docker run --rm --network "$NETWORK" curlimages/curl:8.10.1 "$@"
}

for _ in $(seq 1 60); do
    code="$(probe -s -o /dev/null -w '%{http_code}' --max-time 3 "http://${APP}:8080/" || true)"
    [ -n "$code" ] && [ "$code" != "000" ] && break
    sleep 2
done
[ -n "${code:-}" ] && [ "$code" != "000" ] || { echo "App never became ready"; docker logs "$APP"; exit 1; }

# Reverb needs a moment to listen; retry the upgrade until it answers.
upgrade_status=""
for _ in $(seq 1 30); do
    probe -s -D - -o /dev/null --max-time 3 \
        -H 'Connection: Upgrade' -H 'Upgrade: websocket' \
        -H 'Sec-WebSocket-Version: 13' -H 'Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==' \
        "http://${APP}:8080/app/musicparty?protocol=7" >"$WORK/headers" 2>/dev/null || true
    upgrade_status="$(head -n1 "$WORK/headers" | tr -d '\r' | awk '{print $2}')"
    [ "$upgrade_status" = "101" ] && break
    sleep 2
done

probe -s -w '\n%{http_code}' --max-time 5 "http://${APP}:8080/app/musicparty?protocol=7" >"$WORK/plain" || true
plain_status="$(tail -n1 "$WORK/plain")"

fail=0
if [ "$upgrade_status" != "101" ]; then
    echo "FAIL: websocket upgrade returned '${upgrade_status}', expected 101"; fail=1
else
    echo "OK: websocket upgrade reached Reverb (101)"
fi
if [ "$plain_status" != "404" ] || ! grep -qi '<title>Not Found</title>' "$WORK/plain"; then
    echo "FAIL: plain GET returned '${plain_status}', expected the Laravel 404"; fail=1
else
    echo "OK: plain GET returned the Laravel 404"
fi

if [ "$fail" != "0" ]; then
    docker logs "$APP" | tail -n 30
    docker logs "$REVERB" | tail -n 30
fi
exit "$fail"
