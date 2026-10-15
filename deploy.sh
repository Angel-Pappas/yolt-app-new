#!/usr/bin/env bash
# Deploy the latest `main` to this VM. Run from the live app folder
# (/home/ploi/yolt-app.pappas.yoltobots.click) — never from a dev checkout.
set -euo pipefail
cd "$(dirname "$0")"
# Safety: only the live folder has the production config.
if ! grep -qx 'APP_ENV=production' .env 2>/dev/null; then
    echo "Refusing to deploy: .env is not the live (APP_ENV=production) config." >&2
    exit 1
fi
# Back up the live database first — the deploy aborts if the backup fails.
./backup-db.sh
git pull --ff-only
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
# Database next, so the new PHP code never runs against the old tables even if
# the frontend build below fails.
php artisan migrate --force
# The build generates the frontend's route helpers from `php artisan`'s routes,
# so last deploy's cached routes must go first or new pages fail to build.
php artisan optimize:clear
pnpm install --frozen-lockfile
pnpm run build
php artisan optimize
sudo systemctl reload php8.5-fpm
echo "yolt-app deployed: $(git log -1 --format='%h %s')"
