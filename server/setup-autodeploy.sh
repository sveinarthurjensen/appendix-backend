#!/usr/bin/env bash
# Engangsoppsett (som root): systemd-timer for auto-deploy + diagnose-nøkkel.
set -euo pipefail
ENV=/srv/appendix-backend/.env
cat > /etc/systemd/system/appendix-autodeploy.service <<UNIT
[Unit]
Description=Appendix-backend auto-deploy fra GitHub
After=network-online.target docker.service

[Service]
Type=oneshot
ExecStart=/bin/bash /root/appendix-backend/server/auto-deploy.sh
UNIT
cat > /etc/systemd/system/appendix-autodeploy.timer <<UNIT
[Unit]
Description=Sjekk GitHub for ny appendix-backend-kode hvert 2. minutt

[Timer]
OnBootSec=1min
OnUnitActiveSec=2min

[Install]
WantedBy=timers.target
UNIT
systemctl daemon-reload
systemctl enable --now appendix-autodeploy.timer

if ! grep -q '^DIAG_TOKEN=.\+' "$ENV"; then
  echo "DIAG_TOKEN=$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 32)" >> "$ENV"
fi
mkdir -p /srv/appendix-backend/deploy
rm -f /srv/appendix-backend/deploy/status.json   # tving første deploy
systemctl start appendix-autodeploy.service || true
echo
echo "Auto-deploy aktiv: $(systemctl is-active appendix-autodeploy.timer)"
echo "Diagnose: https://api.appendixholding.no/api/_diag?token=$(grep '^DIAG_TOKEN=' "$ENV" | cut -d= -f2)"
cat /srv/appendix-backend/deploy/status.json 2>/dev/null; echo
