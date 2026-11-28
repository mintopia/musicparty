#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

export SMOKE_IMAGE="${IMAGE:-musicparty:smoke}"
export SMOKE_APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
export SMOKE_APP_URL="http://musicparty.smoke.test:18080"
export SMOKE_OAUTH_URL="http://oauth.smoke.test:19000"
COMPOSE=(docker compose -p mp-image-smoke -f docker-compose.yml)

cleanup() {
    if [ "${KEEP:-0}" != "1" ]; then
        "${COMPOSE[@]}" logs --no-color >smoke-compose.log 2>&1 || true
        "${COMPOSE[@]}" down -v >/dev/null 2>&1 || true
    fi
}
trap cleanup EXIT

expect_boot_failure() {
    local missing="$1"
    shift
    local output status
    set +e
    output="$(timeout 90 docker run --rm "$@" "$SMOKE_IMAGE" php artisan about 2>&1)"
    status=$?
    set -e
    if [ "$status" = "0" ] || ! grep -qi "$missing" <<<"$output"; then
        echo "FAIL: boot without ${missing} should fail naming it (exit ${status})"
        echo "$output" | tail -n 20
        exit 1
    fi
    echo "OK: boot without ${missing} fails with a message naming it"
}

expect_boot_failure APP_URL -e APP_ENV=production -e "APP_KEY=${SMOKE_APP_KEY}"
expect_boot_failure APP_KEY -e APP_ENV=production -e "APP_URL=${SMOKE_APP_URL}"

"${COMPOSE[@]}" up -d

for _ in $(seq 1 90); do
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 3 -H "Host: musicparty.smoke.test:18080" http://127.0.0.1:18080/api/v1/ping || true)"
    [ "$code" = "200" ] && break
    sleep 2
done
[ "$code" = "200" ] || { echo "App never became ready"; "${COMPOSE[@]}" logs app | tail -n 50; exit 1; }

"${COMPOSE[@]}" exec -T app php artisan migrate --force
"${COMPOSE[@]}" exec -T app php artisan db:seed --force
"${COMPOSE[@]}" exec -T app php artisan tinker --execute '
    $provider = \App\Models\SocialProvider::whereCode("laravelpassport")->firstOrFail();
    $provider->forceFill(["enabled" => true, "auth_enabled" => true])->save();
    foreach (["client_id" => "smoke-client", "client_secret" => "smoke-secret", "host" => "'"${SMOKE_OAUTH_URL}"'"] as $code => $value) {
        $provider->settings()->whereCode($code)->firstOrFail()->forceFill(["value" => $value])->save();
    }
'

[ -d node_modules/@playwright ] || npm install --no-audit --no-fund
npx playwright install chromium
npx playwright test
