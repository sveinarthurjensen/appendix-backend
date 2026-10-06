# Appendix Properties → egen Laravel-backend

Generert 5.10.2026 fra Base44-appen «Appendix Properties AS» (66 entiteter, 70 funksjoner, 12 workflows).

| Mappe | Innhold |
|---|---|
| `schema/` | Rå skjemaeksport fra Base44 (kilde for alt under) |
| `generator/generate.py` | Skjema → migrasjoner, modeller, policies, registry. Kjør på nytt ved skjemaendringer i Base44 |
| `laravel/` | Ferdig kode som kopieres inn i Laravel-prosjektet – se `laravel/INSTALL.md` |
| `laravel/GENERATOR_REPORT.md` | Avvik generatoren fant |
| `frontend-shim/base44Client.js` | Erstatter `src/api/base44Client.js` i appen; feature-flag per entitet/funksjon |
| `tools/export_base44.py` | Full dataeksport fra Base44 → én JSON per entitet + `counts.json` for avstemming |

## Hva som er gjort
- 65 tabeller (+ `users`), PostgreSQL. Base44-ID-er beholdes, `app_id` på alle rader, soft delete, `created_date`/`updated_date` som i Base44.
- `array`/`object` → `jsonb`, `date`/`date-time` → `date`/`timestamptz`, `number` → `numeric`, enum → valideringsregel + `ENUMS`-konstant.
- RLS → én policy per modell. 58 av 65 entiteter er admin-only; Booking, Tenant, Property, PropertyAvailability, SelfDeclaration, WebAuthnCredential har rad-regler (`OWNER_FIELDS`) som også filtrerer lister i databasen.
- Generisk API `/api/entities/{Entity}` med Base44-filtersyntaks (`$in`, `$gte`, `$or` …), `/api/functions/{name}` (proxy til Deno-container eller native), `/api/auth/*` (Sanctum, midlertidig e-post/passord – byttes til OIDC/BankID i fase 6).
- `php artisan base44:import` – idempotent upsert med avstemmingstabell.

## Verifisert
- Alle PHP-filer passerer `php -l`.
- DDL-en for alle 68 tabeller kjører feilfritt i PostgreSQL 16.
- Ikke kjørt: selve Laravel-runtime (Packagist er sperret fra min side). Første `php artisan migrate` skjer på serveren – INSTALL.md beskriver det.

## Avvik å være klar over
- `Case` er reservert i PHP → klassen heter `CaseRecord` (entitetsnavn og API-sti er fortsatt `Case`).
- `UserInvite.created_by_id` og feltene `created_at` i Oidc*/WebAuthn*/QRSession/AuditEvent er vanlige kolonner, ikke Laravel-tidsstempler.
- `BrregOppslag` har tabellnavn `brreg_oppslags` (automatisk flertall) – kosmetisk.
- `User` i Base44 er ikke generert som egen entitet; feltene ligger i `users`-tabellen.

## Neste steg
1. Server oppe (`laravel-serveroppsett`), kode inn (INSTALL.md), første admin opprettet, `/api/health` svarer.
2. Eksport fra Base44 (`tools/export_base44.py`) → `base44:import --dry-run` → import. Sammenlign `counts.json` med tabellen kommandoen skriver.
3. Frontend: bytt `base44Client.js`, sett `VITE_LARAVEL_ENTITIES=Property` i staging, klikk gjennom. Utvid listen entitet for entitet.
4. Deno-container for de 70 funksjonene (spor A), deretter OIDC/BankID i Laravel.
