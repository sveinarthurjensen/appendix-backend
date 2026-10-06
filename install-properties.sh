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
  docker compose run --rm app php artisan install:api --no-interaction
fi

cp -r "$CODE/app/." "$SRC/app/"
cp "$CODE/routes/api.php" "$SRC/routes/api.php"
rm -f "$SRC/database/migrations/0001_01_01_000000_create_users_table.php"
cp "$CODE"/database/migrations/*.php "$SRC/database/migrations/"

# services.functions i config/services.php
if ! grep -q "'functions'" "$SRC/config/services.php"; then
  python3 - "$SRC/config/services.php" <<'PY'
import sys,re
p=sys.argv[1]; s=open(p).read()
add="""    'functions' => [
        'url' => env('FUNCTIONS_URL'),
        'token' => env('FUNCTIONS_TOKEN'),
    ],

"""
s=re.sub(r"(return \[\n)", r"\1"+add, s, count=1)
open(p,'w').write(s)
PY
fi

docker compose run --rm app composer dump-autoload --optimize --quiet
echo "Appendix Properties-kode lagt inn i $SRC"
