# Appendix-backend – Laravel på serveren

Ferdig oppsett for fase 1 i migreringsplanen: én Docker Compose-stack per miljø
(staging og prod på hver sin server, eller samme server med to mapper og to domener).

## Innhold
| Fil | Hva |
|---|---|
| `bootstrap-server.sh` | Klargjør fersk Ubuntu 24.04: oppdateringer, deploy-bruker, SSH-herding, ufw, fail2ban, Docker |
| `docker-compose.yml` | php-fpm, nginx, Caddy (TLS), PostgreSQL 16, Redis 7, Horizon, scheduler, nattlig DB-backup |
| `deploy.sh` | `--first-run` oppretter Laravel 12-prosjektet i `./src` med Sanctum, Horizon, spatie/permission, activitylog, backup; ellers vanlig deploy |
| `.env.example` | Alle variabler – kopieres til `.env` |
| `docker/` | Dockerfile (PHP 8.3 + pdo_pgsql/redis/intl/gd), php.ini, nginx, Caddyfile, Postgres-init |

## Første gang
```bash
# 1. Som root på serveren
bash bootstrap-server.sh deploy ~/morten.pub

# 2. Som deploy
cd /srv/appendix-backend
git clone <repo> .            # eller scp denne mappen opp
cp .env.example .env && nano .env     # passord, APP_DOMAIN, APP_URL
bash deploy.sh --first-run
```
DNS for `APP_DOMAIN` må peke på serveren FØR første oppstart, ellers får ikke Caddy sertifikat.

## Senere deploy
```bash
git pull && bash deploy.sh
```

## Daglig drift
```bash
docker compose ps                         # status
docker compose logs -f app horizon        # logger
docker compose exec app php artisan tinker
ls backups/daily                          # DB-dumper (synk til objektlager i EU)
```

## Sikkerhetsvalg som er tatt
- Postgres og Redis har ingen porter mot verden; kun nginx via Caddy på 443.
- SSH kun med nøkkel, root kan ikke logge inn med passord, ufw slipper bare 22/80/443.
- Docker-logger roteres; `expose_php` og `server_tokens` av.
- Alle hemmeligheter ligger i én `.env` utenfor koderepoet (legg `.env` i `.gitignore`).

## Neste steg (fase 2)
`app_id`-kolonne + global tenant-scope på alle modeller, og generatorskriptet
`schema_snapshot.json → migrasjoner/modeller`. Det hører hjemme i repoet, ikke her.
