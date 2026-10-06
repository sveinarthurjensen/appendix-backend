#!/usr/bin/env bash
# Klargjør en fersk Ubuntu 24.04-server (EU) for Appendix-backend.
# Kjøres én gang som root:  bash bootstrap-server.sh <deploy-brukernavn> <ssh-public-key-fil>
set -euo pipefail

DEPLOY_USER="${1:-deploy}"
PUBKEY_FILE="${2:-}"

echo "==> Oppdaterer systemet"
apt-get update && apt-get -y full-upgrade
apt-get -y install ufw fail2ban unattended-upgrades git curl ca-certificates gnupg

echo "==> Automatiske sikkerhetsoppdateringer"
dpkg-reconfigure -f noninteractive unattended-upgrades

echo "==> Deploy-bruker uten passord, kun SSH-nøkkel"
if ! id "$DEPLOY_USER" &>/dev/null; then
  adduser --disabled-password --gecos "" "$DEPLOY_USER"
  usermod -aG sudo "$DEPLOY_USER"
  echo "$DEPLOY_USER ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/"$DEPLOY_USER"
fi
if [[ -n "$PUBKEY_FILE" ]]; then
  install -d -m 700 -o "$DEPLOY_USER" -g "$DEPLOY_USER" /home/"$DEPLOY_USER"/.ssh
  install -m 600 -o "$DEPLOY_USER" -g "$DEPLOY_USER" "$PUBKEY_FILE" /home/"$DEPLOY_USER"/.ssh/authorized_keys
fi

if [[ -s /root/.ssh/authorized_keys ]]; then
  echo "==> Herder SSH (root har SSH-nøkkel, passordinnlogging slås av)"
  sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
  sed -i 's/^#\?PermitRootLogin.*/PermitRootLogin prohibit-password/' /etc/ssh/sshd_config
  systemctl restart ssh
else
  echo "==> HOPPER OVER SSH-herding: root har ingen SSH-nøkkel, passordinnlogging beholdes så dere ikke låses ute."
  echo "    Legg inn nøkkel i /root/.ssh/authorized_keys og kjør skriptet på nytt for å slå av passord."
fi

echo "==> Brannmur: kun SSH, HTTP, HTTPS"
ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

echo "==> fail2ban"
systemctl enable --now fail2ban

if docker compose version >/dev/null 2>&1; then
  echo "==> Docker + Compose finnes allerede ($(docker --version)) – hopper over installasjon"
else
  echo "==> Docker Engine + Compose-plugin"
  install -m 0755 -d /etc/apt/keyrings
  curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --batch --yes --dearmor -o /etc/apt/keyrings/docker.gpg
  chmod a+r /etc/apt/keyrings/docker.gpg
  echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
    > /etc/apt/sources.list.d/docker.list
  apt-get update
  apt-get -y install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi
usermod -aG docker "$DEPLOY_USER"

if [[ ! -f /etc/docker/daemon.json ]]; then
  cat > /etc/docker/daemon.json <<'JSON'
{ "log-driver": "json-file", "log-opts": { "max-size": "20m", "max-file": "5" } }
JSON
fi
systemctl enable --now docker

echo "==> Prosjektmappe"
install -d -o "$DEPLOY_USER" -g "$DEPLOY_USER" /srv/appendix-backend

echo
echo "Ferdig. Neste steg som $DEPLOY_USER:"
echo "  cd /srv/appendix-backend && git clone <repo> . && cp .env.example .env && bash deploy.sh --first-run"
