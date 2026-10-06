#!/usr/bin/env bash
# Legger Laravel-API-et bak serverens eksisterende Caddy (container "caddy", site-filer i /opt/appendix/caddy/sites).
#   bash caddy-site.sh api.appendixholding.no
# Gjør: kobler nginx-containeren vår til Caddy sitt docker-nett, skriver site-fil, reloader Caddy,
# og lukker port 8090 mot internett (ufw + fjerner port-publisering).
set -euo pipefail
DOMAIN="${1:?Bruk: caddy-site.sh <domene>}"
TARGET=/srv/appendix-backend
SITES=/opt/appendix/caddy/sites
CADDY=caddy
NGINX=appendix-backend-nginx-1

[[ $EUID -eq 0 ]] || { echo "Kjør som root"; exit 1; }
docker inspect "$CADDY" >/dev/null 2>&1 || { echo "Fant ikke container '$CADDY'"; exit 1; }
[[ -d "$SITES" ]] || { echo "Fant ikke $SITES"; exit 1; }

PUBLIC_IP=$(curl -s4 --max-time 5 https://ifconfig.me || hostname -I | awk '{print $1}')
RESOLVED=$(getent ahostsv4 "$DOMAIN" | awk '{print $1; exit}' || true)
[[ "$RESOLVED" == "$PUBLIC_IP" ]] || { echo "ADVARSEL: $DOMAIN peker på '${RESOLVED:-ingenting}', ikke $PUBLIC_IP. Lag A-record først."; exit 1; }

echo "==> Kobler $NGINX til Caddy sitt nettverk"
CADDY_NET=$(docker inspect "$CADDY" -f '{{range $k,$v := .NetworkSettings.Networks}}{{$k}}{{"\n"}}{{end}}' | grep -v '^bridge$' | head -1)
[[ -n "$CADDY_NET" ]] || { echo "Fant ikke nettverket til Caddy"; exit 1; }
docker network connect "$CADDY_NET" "$NGINX" 2>/dev/null || echo "   (allerede koblet)"

echo "==> Skriver $SITES/appendix-backend.caddy"
cat > "$SITES/appendix-backend.caddy" <<CADDY
$DOMAIN {
    encode zstd gzip
    header {
        Strict-Transport-Security "max-age=31536000; includeSubDomains"
        X-Content-Type-Options nosniff
        -Server
    }
    reverse_proxy $NGINX:80
}
CADDY
docker exec "$CADDY" caddy reload --config /etc/caddy/Caddyfile --adapter caddyfile

echo "==> Lukker port 8090 mot internett"
cat > "$TARGET/docker-compose.override.yml" <<YML
# Generert av caddy-site.sh: API-et nås via Caddy ($DOMAIN); ingen porter publiseres direkte
services:
  nginx:
    ports: !override []
    networks:
      - default
      - caddy
  caddy:
    profiles: ["disabled"]
networks:
  caddy:
    external: true
    name: $CADDY_NET
YML
chown deploy:deploy "$TARGET/docker-compose.override.yml"
ufw delete allow 8090/tcp >/dev/null 2>&1 || true

sed -i "s|^APP_URL=.*|APP_URL=https://$DOMAIN|" "$TARGET/.env"
cd "$TARGET"
sudo -u deploy -H docker compose up -d nginx
sudo -u deploy -H docker compose run --rm --user "$(id -u deploy):$(id -g deploy)" -e HOME=/tmp app php artisan optimize >/dev/null
docker exec "$CADDY" caddy reload --config /etc/caddy/Caddyfile --adapter caddyfile >/dev/null 2>&1 || true

sleep 5
echo "==> Test"
curl -fsS "https://$DOMAIN/api/health" && echo && echo "API-et svarer på https://$DOMAIN" || echo "Ikke oppe ennå – sertifikat kan ta et minutt. Sjekk: docker logs --tail=30 $CADDY"
