#!/usr/bin/env bash
# Deploy the latest `main` to this VM. Run from the live app folder
# (/home/ploi/yolt-app.pappas.yoltobots.click) — never from a dev checkout.
set -euo pipefail
cd "$(dirname "$0")"
# Back up the live database first — the deploy aborts if the backup fails.
./backup-db.sh
git pull --ff-only
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
pnpm install --frozen-lockfile
pnpm run build
php artisan migrate --force
php artisan optimize
sudo systemctl reload php8.5-fpm
echo "yolt-app deployed: $(git log -1 --format='%h %s')"
