#!/bin/sh
set -eu

cd /var/www/html

php artisan config:clear --ansi
php artisan route:clear --ansi
php artisan view:clear --ansi

# Supabase's shared pooler hostname can vary by infrastructure shard.
# Try the configured host first, then the known EU-West-1 pooler endpoints.
ORIGINAL_DB_HOST="${DB_HOST:-}"
DB_HOST_CANDIDATES="${ORIGINAL_DB_HOST} aws-0-eu-west-1.pooler.supabase.com aws-1-eu-west-1.pooler.supabase.com"

CONNECTED="false"
for candidate in $DB_HOST_CANDIDATES; do
    [ -n "$candidate" ] || continue
    DB_HOST="$candidate"
    export DB_HOST
    if php artisan migrate:status --ansi >/dev/null 2>&1; then
        echo "Database connection successful via $DB_HOST"
        CONNECTED="true"
        break
    fi
done

if [ "$CONNECTED" != "true" ]; then
    echo "Unable to connect to Supabase PostgreSQL through the configured EU pooler endpoints." >&2
    exit 1
fi

php artisan migrate --force --ansi
php artisan storage:link || true
php artisan optimize --ansi

# Keep the scheduler lightweight on Render's 512 MB free instance.
# Run it once per minute instead of keeping a long-lived PHP scheduler
# process in memory. Horizon is intentionally disabled on the free plan.
(
  while true; do
    php artisan schedule:run --no-ansi >/tmp/trypost-scheduler.log 2>&1 || true
    sleep 60
  done
) &

exec apache2-foreground
