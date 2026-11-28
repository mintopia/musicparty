#!/bin/sh
set -eu

image="${1:?usage: verify-no-secrets.sh <image>}"
patterns='swordfish|REVERB_APP_(ID|KEY|SECRET)=.|APP_KEY=.|DB_PASSWORD=.|REDIS_PASSWORD=.'

env_file="$(docker run --rm --entrypoint cat "$image" /app/.env 2>/dev/null || true)"
metadata="$(docker inspect --format '{{json .Config.Env}} {{json .Config.Cmd}}' "$image"; docker history --no-trunc --format '{{.CreatedBy}}' "$image")"
combined="$(printf '%s\n%s\n' "$env_file" "$metadata")"

if printf '%s\n' "$combined" | grep -Eq "$patterns"; then
    echo "Image $image contains baked secret values:" >&2
    printf '%s\n' "$combined" | grep -E "$patterns" >&2
    exit 1
fi

echo "Image $image contains no baked secrets."
