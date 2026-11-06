#!/usr/bin/env bash
# ============================================================
#  Toll Management - central server deployment (Ubuntu / any Linux VPS)
#  Prerequisites: PHP 8.3 (+ mysql, mbstring, intl, gd, zip), Composer, MySQL 8,
#  Nginx/Apache pointing to ./public, .env created from deploy/central/.env.central.example
#  Usage: bash deploy/central/deploy.sh   (safe to re-run for every update)
# ============================================================
set -euo pipefail
cd "$(dirname "$0")/../.."

[ -f .env ] || { echo "Copy deploy/central/.env.central.example to .env first"; exit 1; }

php artisan down || true

composer install --no-dev --optimize-autoloader --no-interaction
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan filament:assets
php artisan optimize
php artisan storage:link 2>/dev/null || true

php artisan up

if ! php artisan tinker --execute 'exit(App\Models\User::query()->exists() ? 0 : 1);' >/dev/null 2>&1; then
    echo "No users yet - create the first Super Admin:"
    php artisan toll:create-admin
fi

echo
echo "Deployed. Remember (once):"
echo "  * cron:  * * * * * cd $(pwd) && php artisan schedule:run >> /dev/null 2>&1"
echo "  * HTTPS: booths send the sync token, so serve the site over TLS (e.g. certbot)."
echo "  * Create one booth per toll booth (Configuration > Booths) with the same code as its TOLL_BOOTH_CODE."
