#!/bin/sh
set -e

# Optimize the application
php /app/artisan optimize

exec "$@"
