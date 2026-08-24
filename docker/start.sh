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
# Run it once per minute and expose command output so scheduled automation
# failures are visible in Render logs.
(
  while true; do
    echo "Running TryPost scheduler..."
    if php artisan schedule:run --no-ansi; then
      SCHEDULE_EXIT=0
    else
      SCHEDULE_EXIT=$?
      echo "TryPost scheduler exited with code ${SCHEDULE_EXIT}; continuing..." >&2
    fi
    sleep 60
done
) &

# Process Laravel's database-backed publishing and AI jobs on the same instance.
# AI generation jobs use the ai queue; social publishing jobs use per-platform
# queues. Do not let the shell's `set -e` kill the restart loop when queue:work
# exits with an error; capture the exit code and restart explicitly.
(
  while true; do
    echo "Starting TryPost queue worker..."
    set +e
    php artisan queue:work database \
      --queue=ai,default,social-linkedin,social-linkedin-page,social-x,social-tiktok,social-youtube,social-facebook,social-instagram,social-instagram-facebook,social-threads,social-pinterest,social-bluesky,social-mastodon,social-telegram,social-discord \
      --sleep=3 --tries=3 --timeout=900 --max-time=86400 --no-interaction --no-ansi
    EXIT_CODE=$?
    set -e
    echo "TryPost queue worker exited with code ${EXIT_CODE}; restarting in 5 seconds..." >&2
    sleep 5
  done
) &

exec apache2-foreground
