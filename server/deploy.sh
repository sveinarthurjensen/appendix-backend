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

docker compose build --pull

if $FIRST_RUN; then
  if [[ ! -f src/artisan ]]; then
    echo "==> Oppretter Laravel 12-prosjekt i ./src"
    mkdir -p src
    docker compose run --rm --no-deps app composer create-project laravel/laravel:^12.0 /tmp/laravel
    docker compose run --rm --no-deps app sh -c 'cp -a /tmp/laravel/. /var/www/html/'
    echo "==> Installerer grunnpakkene fra planen"
    docker compose run --rm --no-deps app composer require \
      laravel/sanctum laravel/horizon \
      spatie/laravel-permission spatie/laravel-activitylog spatie/laravel-backup \
      league/flysystem-aws-s3-v3
  fi
  # Laravel skal lese server-.env – symlink så én fil styrer alt
  ln -sf ../.env src/.env
fi

docker compose up -d postgres redis
docker compose run --rm app composer install --no-dev --optimize-autoloader --no-interaction

if $FIRST_RUN; then
  docker compose run --rm app php artisan key:generate --force
  docker compose run --rm app php artisan horizon:install
  docker compose run --rm app php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
  docker compose run --rm app php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
fi

docker compose run --rm app php artisan migrate --force
docker compose run --rm app php artisan optimize

docker compose up -d
docker compose exec horizon php artisan horizon:terminate || true   # Horizon starter på nytt med ny kode
docker compose ps
echo "==> Deploy ferdig: $(grep ^APP_URL .env)"
