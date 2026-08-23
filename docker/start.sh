#!/bin/sh
set -eu

cd /var/www/html

php artisan config:clear --ansi
php artisan route:clear --ansi
php artisan view:clear --ansi
php artisan migrate --force --ansi
php artisan storage:link || true
php artisan optimize --ansi

# Keep the scheduler and queue worker alive alongside Apache on the free web service.
php artisan schedule:work >/tmp/trypost-scheduler.log 2>&1 &
php artisan horizon >/tmp/trypost-horizon.log 2>&1 &

exec apache2-foreground
