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
cp "$CODE"/resources -r "$SRC/" 2>/dev/null || true

docker compose run --rm --no-deps --user "$(id -u):$(id -g)" -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer app composer dump-autoload --optimize --quiet
echo "Appendix Properties-kode lagt inn i $SRC"
