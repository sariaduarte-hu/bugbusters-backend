#!/usr/bin/env bash
# Gebruik op de server, in de projectmap:  ./deploy.sh
set -euo pipefail
cd "$(dirname "$0")"

if [ ! -f .env ]; then
  echo "Geen .env gevonden. Doe eerst: cp .env.example .env  en vul DB_USERNAME en DB_PASSWORD in."
  exit 1
fi

if grep -qE '^DB_(USERNAME|PASSWORD)=$' .env; then
  echo "DB_USERNAME of DB_PASSWORD is nog leeg in .env."
  exit 1
fi

git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction

# Maak APP_KEY alleen aan als die nog leeg is
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force

php artisan down --retry=10 || true
trap 'php artisan up' EXIT

php artisan migrate --force
php artisan config:cache
php artisan route:cache

echo "Klaar. De API staat weer online."
