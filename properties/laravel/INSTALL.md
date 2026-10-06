# Legge koden inn i Laravel-prosjektet

Etter `deploy.sh --first-run` finnes `/srv/appendix-backend/src`. Kopier inn:

```bash
cd /srv/appendix-backend
cp -r laravel/app/* src/app/
cp -r laravel/database/migrations/* src/database/migrations/
cp laravel/routes/api.php src/routes/api.php
rm src/database/migrations/0001_01_01_000000_create_users_table.php   # erstattet av vår
```

Aktiver API-ruter og Sanctum (Laravel 12 har ikke api.php som standard):

```bash
docker compose run --rm app php artisan install:api --no-interaction   # lager routes/api.php + sanctum-migrasjon
cp laravel/routes/api.php src/routes/api.php                           # vår versjon over den genererte
```

Legg `functions`-blokken fra `config-services-addition.php` inn i `src/config/services.php`,
og i `.env`: `FUNCTIONS_URL=` (tom inntil Deno-containeren er oppe).

Kjør migrasjonene og lag første admin:

```bash
docker compose run --rm app php artisan migrate --force
docker compose run --rm app php artisan tinker --execute="
  \App\Models\User::create(['id'=>strtolower(\Str::ulid()),'app_id'=>'appendix_properties','email'=>'svein.arthur.jensen@appendixholding.no','full_name'=>'Svein Arthur Jensen','role'=>'admin','password'=>'BYTT-MEG']);"
```

Test:

```bash
curl -s https://api-staging.appendixholding.no/api/health
TOKEN=$(curl -s -X POST https://api-staging.appendixholding.no/api/auth/login -H 'Content-Type: application/json' \
  -d '{"email":"svein.arthur.jensen@appendixholding.no","password":"BYTT-MEG"}' | jq -r .token)
curl -s https://api-staging.appendixholding.no/api/entities/Property -H "Authorization: Bearer $TOKEN"
```

Importer data (etter eksport med `tools/export_base44.py`):

```bash
docker compose run --rm -v $PWD/export:/export app php artisan base44:import /export --dry-run
docker compose run --rm -v $PWD/export:/export app php artisan base44:import /export
```
