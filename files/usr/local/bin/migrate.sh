#!/usr/bin/env sh

set -x

if [ "$MIGRATION_ENABLED" == "true" ]; then
    cd /var/www || exit
    ./artisan migrate --force
fi
