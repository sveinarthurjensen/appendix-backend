#!/usr/bin/env bash
# Kjøres av systemd-timeren appendix-autodeploy (hvert 2. min). Henter main fra GitHub og
# kjører installeren når det er nye commits. Skriver status til /srv/appendix-backend/deploy/status.json,
# som diagnose-endepunktet /api/_diag leser.
set -uo pipefail
REPO=/root/appendix-backend
OUT=/srv/appendix-backend/deploy
mkdir -p "$OUT"; chmod 755 "$OUT"
exec 9>/run/appendix-autodeploy.lock; flock -n 9 || exit 0

cd "$REPO"
git fetch -q origin main || { echo "{\"status\":\"fetch_failed\",\"at\":\"$(date -Is)\"}" > "$OUT/status.json"; exit 1; }
LOCAL=$(git rev-parse HEAD); REMOTE=$(git rev-parse origin/main)
if [[ "$LOCAL" == "$REMOTE" && -f "$OUT/status.json" ]] && grep -q '"status":"ok"' "$OUT/status.json"; then
  exit 0
fi

git reset -q --hard origin/main
COMMIT=$(git rev-parse --short HEAD); MSG=$(git log -1 --pretty=%s | tr '"' "'")
echo "{\"status\":\"deploying\",\"commit\":\"$COMMIT\",\"message\":\"$MSG\",\"at\":\"$(date -Is)\"}" > "$OUT/status.json"

if bash installer.sh > "$OUT/last-deploy.log" 2>&1 && grep -q "API svarer" "$OUT/last-deploy.log"; then
  STATUS=ok
else
  STATUS=failed
fi
chmod 644 "$OUT"/*
echo "{\"status\":\"$STATUS\",\"commit\":\"$COMMIT\",\"message\":\"$MSG\",\"at\":\"$(date -Is)\"}" > "$OUT/status.json"
