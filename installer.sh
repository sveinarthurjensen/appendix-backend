#!/usr/bin/env bash
# Appendix-backend – alt-i-ett-installasjon på en fersk Ubuntu-server.
#
#   bash appendix-backend/installer.sh                      -> kjører på http://<server-ip>  (uten TLS)
#   bash appendix-backend/installer.sh api.appendixholding.no -> TLS via Let's Encrypt (DNS må peke hit først)
#   bash appendix-backend/installer.sh --port 8090           -> uten Caddy: nginx direkte på http://<ip>:8090
#                                                               (når noe annet allerede bruker 80/443 på serveren)
#
# Kjøres som root. Idempotent: kan kjøres på nytt, hopper over det som er gjort.
set -euo pipefail

DOMAIN=""; PORT=""
while [[ $# -gt 0 ]]; do
  case "$1" in
    --port) PORT="$2"; shift 2 ;;
    *) DOMAIN="$1"; shift ;;
  esac
done
HERE="$(cd "$(dirname "$0")" && pwd)"
TARGET=/srv/appendix-backend
DEPLOY_USER=deploy
LOG=/root/appendix-backend-install.log
exec > >(tee -a "$LOG") 2>&1

say() { echo; echo "==> $*"; }
[[ $EUID -eq 0 ]] || { echo "Kjør som root"; exit 1; }

PUBLIC_IP=$(curl -s4 --max-time 5 https://ifconfig.me || hostname -I | awk '{print $1}')
if [[ -n "$DOMAIN" ]]; then
  RESOLVED=$(getent ahostsv4 "$DOMAIN" | awk '{print $1; exit}' || true)
  if [[ "$RESOLVED" != "$PUBLIC_IP" ]]; then
    echo "ADVARSEL: $DOMAIN peker på '${RESOLVED:-ingenting}', serveren er $PUBLIC_IP. Caddy får ikke sertifikat før DNS er riktig."
    read -r -p "Fortsette likevel? [j/N] " a; [[ "$a" =~ ^[jJyY]$ ]] || exit 1
  fi
  APP_DOMAIN="$DOMAIN"; APP_URL="https://$DOMAIN"
elif [[ -n "$PORT" ]]; then
  APP_DOMAIN="-"; APP_URL="http://$PUBLIC_IP:$PORT"
else
  APP_DOMAIN="http://$PUBLIC_IP"; APP_URL="http://$PUBLIC_IP"
fi

say "Sjekker port 80/443"
if grep -q "caddy-site.sh" "$TARGET/docker-compose.override.yml" 2>/dev/null; then
  echo "API-et går via serverens Caddy (caddy-site.sh) – ingen portsjekk"
  PORT=""
elif [[ -n "$PORT" ]]; then
  echo "Hopper over – Caddy brukes ikke, nginx eksponeres på port $PORT"
elif ss -ltnp 2>/dev/null | grep -qE ':(80|443) ' && ! docker ps --format '{{.Names}}' 2>/dev/null | grep -q appendix-backend-caddy; then
  echo "Noe annet lytter allerede på 80/443:"; ss -ltnp | grep -E ':(80|443) '
  echo "Stopp det, eller si fra til Claude så tilpasses Caddy-delen."; exit 1
fi

say "Grunnoppsett av serveren (pakker, brannmur, Docker, deploy-bruker)"
PUBKEY=""; [[ -f /root/.ssh/authorized_keys ]] && PUBKEY=/root/.ssh/authorized_keys
bash "$HERE/server/bootstrap-server.sh" "$DEPLOY_USER" "$PUBKEY"

say "Legger filene i $TARGET"
mkdir -p "$TARGET"
cp -r "$HERE/server/." "$TARGET/"
rm -rf "$TARGET/properties" && cp -r "$HERE/properties" "$TARGET/properties"
cp "$HERE/install-properties.sh" "$TARGET/install-properties.sh"

say "Lager .env"
if [[ ! -f "$TARGET/.env" ]]; then
  gen() { tr -dc 'A-Za-z0-9' </dev/urandom | head -c 40; }
  sed -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$(gen)|" \
      -e "s|^REDIS_PASSWORD=.*|REDIS_PASSWORD=$(gen)|" \
      -e "s|^APP_DOMAIN=.*|APP_DOMAIN=$APP_DOMAIN|" \
      -e "s|^APP_URL=.*|APP_URL=$APP_URL|" \
      -e "s|^APP_ENV=.*|APP_ENV=production|" \
      "$TARGET/.env.example" > "$TARGET/.env"
  echo "FUNCTIONS_URL=" >> "$TARGET/.env"
  echo "FUNCTIONS_TOKEN=$(gen)" >> "$TARGET/.env"
  chmod 600 "$TARGET/.env"
else
  echo ".env finnes – beholdes"
fi
# APP_KEY må være en ekte nøkkel (tidligere .env.example hadde en inline-kommentar som ble lest som verdi)
if ! grep -q '^APP_KEY=base64:' "$TARGET/.env"; then
  sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(head -c 32 /dev/urandom | base64)|" "$TARGET/.env"
  echo "Satte ny APP_KEY"
fi
if ! grep -q '^BACKUP_ENCRYPTION_KEY=.\+' "$TARGET/.env"; then
  echo "BACKUP_ENCRYPTION_KEY=$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 48)" >> "$TARGET/.env"
  echo "Genererte BACKUP_ENCRYPTION_KEY – TA KOPI av denne fra .env og legg i passordhvelvet; uten den kan ikke OneDrive-backup gjenopprettes"
fi
if [[ -n "$PORT" ]] && grep -q "caddy-site.sh" "$TARGET/docker-compose.override.yml" 2>/dev/null; then
  echo "Caddy-oppsett finnes allerede (caddy-site.sh) – beholder det, ignorerer --port"
  PORT=""
fi
if [[ -n "$PORT" ]]; then
  cat > "$TARGET/docker-compose.override.yml" <<YML
# Generert av installer.sh --port $PORT: ingen Caddy, nginx direkte på port $PORT
services:
  nginx:
    ports: !override
      - "0.0.0.0:$PORT:80"
  caddy:
    profiles: ["disabled"]
YML
  ufw allow "$PORT"/tcp >/dev/null || true
fi
mkdir -p "$TARGET/src" "$TARGET/backups" "$TARGET/deploy"
chown -R "$DEPLOY_USER:$DEPLOY_USER" "$TARGET"

if [[ ! -f "$TARGET/src/artisan" ]]; then
  say "Laravel: bygger image, oppretter prosjekt, starter stacken"
  sudo -u "$DEPLOY_USER" -H bash "$TARGET/deploy.sh" --first-run
else
  say "Laravel-prosjektet finnes allerede – hopper til Properties-koden"
  cd "$TARGET" && sudo -u "$DEPLOY_USER" -H docker compose up -d postgres redis
fi

say "Legger inn Appendix Properties-koden"
sudo -u "$DEPLOY_USER" -H bash "$TARGET/install-properties.sh" "$TARGET"

say "Kjører migrasjoner"
cd "$TARGET"
if [[ ! -f "$TARGET/.properties-migrated" ]]; then
  # Første gang: Laravel sin standard users-tabell ble laget av deploy.sh før vår kode kom inn – bygg databasen på nytt
  sudo -u "$DEPLOY_USER" -H docker compose run --rm --user "$(id -u $DEPLOY_USER):$(id -g $DEPLOY_USER)" -e HOME=/tmp app php artisan migrate:fresh --force
  touch "$TARGET/.properties-migrated"
else
  sudo -u "$DEPLOY_USER" -H docker compose run --rm --user "$(id -u $DEPLOY_USER):$(id -g $DEPLOY_USER)" -e HOME=/tmp app php artisan migrate --force
fi
sudo -u "$DEPLOY_USER" -H docker compose run --rm --user "$(id -u $DEPLOY_USER):$(id -g $DEPLOY_USER)" -e HOME=/tmp app php artisan optimize
sudo -u "$DEPLOY_USER" -H docker compose up -d
# .env er bind-montert som enkeltfil; sed -i gir ny inode som kjørende containere ikke ser → alltid gjenskap app-containerne
sudo -u "$DEPLOY_USER" -H docker compose up -d --force-recreate app horizon scheduler
sudo -u "$DEPLOY_USER" -H docker compose restart nginx >/dev/null

say "Oppretter første admin-bruker"
ADMIN_EMAIL="svein.arthur.jensen@appendixholding.no"
ADMIN_OUT=$(sudo -u "$DEPLOY_USER" -H docker compose run --rm --user "$(id -u $DEPLOY_USER):$(id -g $DEPLOY_USER)" -e HOME=/tmp app \
  php artisan app:make-admin "$ADMIN_EMAIL" --name="Svein Arthur Jensen" --if-missing 2>&1) || { echo "$ADMIN_OUT"; echo "FEIL ved oppretting av admin"; exit 1; }
echo "$ADMIN_OUT" | grep -v PASSORD
ADMIN_PASS=$(echo "$ADMIN_OUT" | sed -n 's/^PASSORD: //p')

APP_URL=$(grep ^APP_URL= "$TARGET/.env" | cut -d= -f2-)
say "Helsesjekk"
sleep 5
if curl -fsS "$APP_URL/api/health" ; then echo; echo "API svarer."; else echo "API svarer ikke ennå – sjekk: cd $TARGET && docker compose logs --tail=100 app nginx caddy"; fi

cat <<DONE

=========================================================
  Appendix-backend er installert.

  URL:        $APP_URL
  Mappe:      $TARGET
  Admin:      $ADMIN_EMAIL${ADMIN_PASS:+
  Passord:    $ADMIN_PASS      (bytt ved første innlogging)}
  Logg:       $LOG

  Test:
    TOKEN=\$(curl -s -X POST $APP_URL/api/auth/login -H 'Content-Type: application/json' \\
      -d '{"email":"$ADMIN_EMAIL","password":"<passord>"}' | jq -r .token)
    curl -s $APP_URL/api/entities/Property -H "Authorization: Bearer \$TOKEN"

  Dataimport (når eksport fra Base44 ligger i $TARGET/export):
    cd $TARGET && docker compose run --rm -v \$PWD/export:/export app php artisan base44:import /export

  Drift:  cd $TARGET && docker compose ps | logs -f app horizon
=========================================================
DONE
