#!/usr/bin/env bash
# Setter/oppdaterer nøkler i /srv/appendix-backend/.env uten å vise verdien i terminalhistorikken,
# og laster konfigurasjonen på nytt.
#   bash set-env.sh AZURE_TENANT_ID AZURE_CLIENT_ID AZURE_CLIENT_SECRET      (spør om hver verdi, skjult)
#   bash set-env.sh                                                           (viser hvilke nøkler som mangler)
set -euo pipefail
ENV=/srv/appendix-backend/.env
KEYS=(SVEVE_USERNAME SVEVE_PASSWORD ANTHROPIC_API_KEY GOOGLE_MAPS_API_KEY MAIL_USERNAME MAIL_PASSWORD
      AZURE_TENANT_ID AZURE_CLIENT_ID AZURE_CLIENT_SECRET OIDC_ISSUER ONEDRIVE_USER_EMAIL BACKUP_ENCRYPTION_KEY)

if [[ $# -eq 0 ]]; then
  echo "Status for nøkler i $ENV:"
  for k in "${KEYS[@]}"; do
    v=$(grep -E "^$k=" "$ENV" 2>/dev/null | cut -d= -f2- || true)
    [[ -n "$v" ]] && echo "  ✓ $k" || echo "  – $k (mangler)"
  done
  echo; echo "Sett: bash $0 NØKKEL [NØKKEL …]"; exit 0
fi

if [[ ! -t 0 ]]; then
  echo "Ingen terminal. Kjør med: ssh -t root@<server> \"bash $0 $*\""; exit 1
fi
printf '\e[?2004l'   # slå av «bracketed paste» så innlimt tekst ikke får [200~ … [201~ rundt seg
for k in "$@"; do
  read -r -s -p "$k: " v; echo
  # fjern evt. bracketed-paste-koder og omkringliggende mellomrom likevel
  v=$(printf '%s' "$v" | sed -e 's/\x1b\[20[01]~//g' -e 's/\[20[01]~//g' -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')
  [[ -n "$v" ]] || { echo "  (tom – hoppet over)"; continue; }
  if grep -qE "^$k=" "$ENV"; then
    sed -i "s|^$k=.*|$k=$v|" "$ENV"
  else
    echo "$k=$v" >> "$ENV"
  fi
  echo "  satt"
done

# rydd opp i eventuelle tidligere innlimte bracketed-paste-koder i .env
sed -i -e 's/\x1b\[20[01]~//g' -e 's/\[20[01]~//g' "$ENV"

cd /srv/appendix-backend
sudo -u deploy -H docker compose run --rm --user "$(id -u deploy):$(id -g deploy)" -e HOME=/tmp app php artisan optimize >/dev/null
sudo -u deploy -H docker compose up -d --force-recreate app horizon scheduler >/dev/null
sudo -u deploy -H docker compose restart nginx >/dev/null
echo "Konfigurasjon lastet på nytt."
