#!/usr/bin/env bash
# Første oppstart og senere deploy. Kjøres som deploy-brukeren i /srv/appendix-backend.
#   bash deploy.sh --first-run    oppretter Laravel-prosjektet i ./src og setter opp alt
#   bash deploy.sh                vanlig deploy etter git pull
set -euo pipefail
cd "$(dirname "$0")"

FIRST_RUN=false
[[ "${1:-}" == "--first-run" ]] && FIRST_RUN=true

[[ -f .env ]] || { echo "Mangler .env – kopier .env.example og fyll inn passord/domene"; exit 1; }
grep -q "BYTT_MEG" .env && { echo "Bytt ut BYTT_MEG-verdiene i .env først"; exit 1; } || true

# Bygge-/artisan-steg kjøres som host-brukeren slik at filene i ./src eies av deploy, ikke www-data.
ME="$(id -u):$(id -g)"
run() { docker compose run --rm --no-deps --user "$ME" -e COMPOSER_HOME=/tmp/composer -e HOME=/tmp app "$@"; }
runapp() { docker compose run --rm --user "$ME" -e COMPOSER_HOME=/tmp/composer -e HOME=/tmp app "$@"; }

# ./src må finnes og eies av oss FØR Docker rører den – ellers oppretter Docker den som root ved første mount
mkdir -p src backups

docker compose build --pull

# storage-volumet opprettes root-eid av Docker – gjør det (og /var/www/html) skrivbart for oss og www-data før noe annet
docker compose run --rm --no-deps --user root app sh -c "chown $ME /var/www/html && mkdir -p storage && chown -R $ME storage && chmod -R a+rwX storage"

if $FIRST_RUN && [[ ! -f src/artisan ]]; then
  echo "==> Oppretter Laravel 12-prosjekt i ./src (uten post-install-skript – de kjøres styrt under)"
  run sh -c 'composer create-project laravel/laravel:^12.0 /tmp/laravel --no-scripts --no-interaction --prefer-dist \
             && cp -a /tmp/laravel/. /var/www/html/'
  echo "==> Installerer grunnpakkene fra planen"
  run composer require --no-interaction --no-scripts \
      laravel/sanctum laravel/horizon \
      spatie/laravel-permission spatie/laravel-activitylog spatie/laravel-backup \
      league/flysystem-aws-s3-v3
  rm -f src/.env src/database/database.sqlite
fi

docker compose up -d postgres redis
runapp composer install --no-dev --optimize-autoloader --no-interaction

if $FIRST_RUN; then
  grep -q '^APP_KEY=base64:' .env || runapp php artisan key:generate --force
  runapp php artisan horizon:install
  runapp php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --no-interaction
  runapp php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations" --no-interaction
fi

# storage og bootstrap/cache må være skrivbare for www-data (php-fpm) i containeren
chmod -R a+rwX src/bootstrap/cache
docker compose run --rm --no-deps --user root app sh -c 'mkdir -p storage/framework/{cache,sessions,views} storage/logs storage/app && chown -R www-data:www-data storage && chmod -R a+rwX storage'

runapp php artisan migrate --force
runapp php artisan optimize

docker compose up -d
docker compose restart nginx >/dev/null   # sikrer at nginx ser ny app-container
docker compose exec -T horizon php artisan horizon:terminate >/dev/null 2>&1 || true   # Horizon starter på nytt med ny kode
docker compose ps
echo "==> Deploy ferdig: $(grep ^APP_URL .env)"
