#!/usr/bin/env bash
# Kopierer generert Laravel-kode for Appendix Properties inn i ./src. Kjøres av installer.sh,
# eller alene som deploy-bruker etter regenerering:  bash install-properties.sh /srv/appendix-backend
set -euo pipefail
TARGET="${1:-/srv/appendix-backend}"
SRC="$TARGET/src"
CODE="$TARGET/properties/laravel"
cd "$TARGET"

# API-ruter + Sanctum (Laravel 12 har ikke dette som standard)
if [[ ! -f "$SRC/routes/api.php" ]]; then
  docker compose run --rm --user "$(id -u):$(id -g)" -e HOME=/tmp app php artisan install:api --no-interaction
fi

cp -r "$CODE/app/." "$SRC/app/"
cp "$CODE/routes/api.php" "$SRC/routes/api.php"
cp "$CODE/routes/console.php" "$SRC/routes/console.php"
# Web-ruter (OIDC-utsteder + Entra/BankID-innlogging, sesjonsbasert) erstatter Laravel sin standard web.php.
# NB: CSRF-unntak for token-endepunktet må ligge i bootstrap/app.php (gjøres én gang, manuelt):
#   $middleware->validateCsrfTokens(except: ['oidc/token', 'functions/oidcToken']);
cp "$CODE/routes/web.php" "$SRC/routes/web.php"
if ! grep -q "oidc/token" "$SRC/bootstrap/app.php" 2>/dev/null; then
  echo "ADVARSEL: bootstrap/app.php mangler CSRF-unntak for oidc/token – se kommentar i routes/web.php"
fi
# Registrer FunctionsServiceProvider (observers m.m.)
if ! grep -q FunctionsServiceProvider "$SRC/bootstrap/providers.php"; then
  sed -i "s|App\\\\Providers\\\\AppServiceProvider::class,|App\\\\Providers\\\\AppServiceProvider::class,\n    App\\\\Providers\\\\FunctionsServiceProvider::class,|" "$SRC/bootstrap/providers.php"
fi
rm -f "$SRC/database/migrations/0001_01_01_000000_create_users_table.php"
cp "$CODE"/database/migrations/*.php "$SRC/database/migrations/"

# Tjenestekonfig: alle nøkler fra properties/laravel/config-services.php merges inn i config/services.php
python3 - "$SRC/config/services.php" "$CODE/config-services.php" <<'PY'
import sys,re
p,src=sys.argv[1],sys.argv[2]
s=open(p).read(); add=open(src).read()
body=add[add.index('return [')+len('return ['):add.rindex('];')]
# fjern tidligere innsatt blokk
s=re.sub(r"\n    // --- appendix-backend start ---.*?// --- appendix-backend slutt ---\n", "\n", s, flags=re.S)
s=re.sub(r"(return \[\n)", r"\1    // --- appendix-backend start ---"+body.replace('\\','\\\\')+"    // --- appendix-backend slutt ---\n", s, count=1)
open(p,'w').write(s)
PY
# Blade-maler (e-post + OIDC innloggings-/feilsider): resources/views/** kopieres inn, eksisterende filer overskrives
mkdir -p "$SRC/resources/views"
cp -r "$CODE/resources/views/." "$SRC/resources/views/"

# CSRF-unntak for OIDC token-endepunktet (bootstrap/app.php) – idempotent
python3 - "$SRC/bootstrap/app.php" <<'PY'
import sys,re
p=sys.argv[1]; s=open(p).read()
if 'validateCsrfTokens' not in s:
    s=s.replace("->withMiddleware(function (Middleware $middleware): void {",
                "->withMiddleware(function (Middleware $middleware): void {\n        $middleware->validateCsrfTokens(except: ['oidc/token', 'functions/oidcToken']);",1)
    open(p,'w').write(s)
PY

docker compose run --rm --no-deps --user "$(id -u):$(id -g)" -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer app composer dump-autoload --optimize --quiet
echo "Appendix Properties-kode lagt inn i $SRC"
