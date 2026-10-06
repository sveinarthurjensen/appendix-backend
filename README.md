# appendix-backend

Egen Laravel-backend for Appendix Holding-appene (EU-server, Docker). Første app ut: Appendix Properties.

## Installasjon på ny server (Ubuntu 22.04/24.04, som root)

```bash
apt-get install -y git
git clone https://github.com/<org>/appendix-backend.git /root/appendix-backend
bash /root/appendix-backend/installer.sh                      # http://<server-ip>
bash /root/appendix-backend/installer.sh api.appendixholding.no   # med TLS (DNS må peke hit)
```

Admin-passord skrives nederst i outputen. Logg: `/root/appendix-backend-install.log`.

## Oppdatering senere

```bash
cd /root/appendix-backend && git pull
sudo -u deploy bash /srv/appendix-backend/install-properties.sh /srv/appendix-backend   # ny generert kode
cd /srv/appendix-backend && sudo -u deploy bash deploy.sh                                # migrasjoner + restart
```

## Struktur

| Mappe | Innhold |
|---|---|
| `installer.sh` | Alt-i-ett: server, Docker-stack, Laravel, Properties-kode, migrasjoner, admin |
| `install-properties.sh` | Legger generert kode inn i `/srv/appendix-backend/src` |
| `server/` | Docker Compose-stack (php-fpm, nginx, Caddy, Postgres 16, Redis, Horizon, scheduler, backup) |
| `properties/` | Appendix Properties: skjema, generator, generert Laravel-kode, frontend-shim, eksportskript |

Rører ikke Base44. Appene kjører som før til `VITE_LARAVEL_ENTITIES` settes i frontenden.
