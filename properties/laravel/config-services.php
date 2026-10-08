<?php
// Legges inn i config/services.php av install-properties.sh (nøkkelen 'appendix' merges inn)
return [
    // scheduler_enabled: planlagte jobber (routes/console.php) kjører bare når SCHEDULER_ENABLED=true. Skal være av så lenge
    // Base44 fortsatt kjører de samme workflowene (ellers får mottakere SMS/e-post/varsler i duplikat). Slås på ved cutover.
    'functions' => ['url' => env('FUNCTIONS_URL'), 'token' => env('FUNCTIONS_TOKEN'), 'scheduler_enabled' => env('SCHEDULER_ENABLED', false)],
    'sveve' => ['username' => env('SVEVE_USERNAME'), 'password' => env('SVEVE_PASSWORD')],
    'anthropic' => ['key' => env('ANTHROPIC_API_KEY'), 'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5')],
    'google_maps' => ['key' => env('GOOGLE_MAPS_API_KEY')],
    'brreg' => ['base' => env('BRREG_BASE', 'https://data.brreg.no/enhetsregisteret/api')],
    'webhook' => ['url' => env('WEBHOOK_URL')],
    // onedrive_user: brukeren hvis OneDrive backupen lastes opp til (azureOneDriveBackup). Krever Files.ReadWrite.All (application).
    'graph' => ['tenant' => env('AZURE_TENANT_ID'), 'client_id' => env('AZURE_CLIENT_ID'), 'client_secret' => env('AZURE_CLIENT_SECRET'), 'onedrive_user' => env('ONEDRIVE_USER_EMAIL')],
    // azureOneDriveBackup: katalogen pgbackup-containeren skriver pg_dump-filer til (montert read-only i app/scheduler), og målmappe i OneDrive.
    'backup' => ['encryption_key' => env('BACKUP_ENCRYPTION_KEY'), 'dir' => env('BACKUP_DIR', '/backups'), 'onedrive_folder' => env('ONEDRIVE_BACKUP_FOLDER', 'Backups/AppendixProperties')],
    // sendToHolding / syncAllToMaster: Appendix Holding-appen – peker på Base44 inntil Holding også er flyttet.
    'holding' => [
        'locations_url' => env('HOLDING_LOCATIONS_URL', 'https://api.base44.app/api/apps/692a2988474b6d9f2ec1b7e6/functions/receiveLocationsData'),
        'external_data_url' => env('HOLDING_EXTERNAL_DATA_URL', 'https://api.base44.app/api/apps/692a2988474b6d9f2ec1b7e6/functions/receiveExternalData'),
        'company_id' => env('HOLDING_COMPANY_ID', '6a25fb193131df935302c3c2'),
        'master_sync_token' => env('MASTER_SYNC_TOKEN'),
        'master_hub_url' => env('MASTER_HUB_URL'),
        'master_hub_url_users' => env('MASTER_HUB_URL_USERS'),
        'app_id' => env('BASE44_APP_ID', '692a283741b5c0d24fceeeb9'),
    ],
    // contractDeadlineReminders / kpiRentAdjustmentReminder: admin som får påminnelsene (var hardkodet i originalen).
    'reminders' => ['admin_email' => env('REMINDER_ADMIN_EMAIL', 'svein.arthur.jensen@appendixholding.no'), 'admin_phone' => env('REMINDER_ADMIN_PHONE', '+4790620833')],
    // runSecurityAudit: flere admin-brukere enn dette gir warning.
    'security' => ['max_admins' => (int) env('SECURITY_MAX_ADMINS', 5)],
    // BankID via Signicat (konsulenter, pasienter/parter). discovery_url f.eks. https://<konto>.app.signicat.com/auth/open/.well-known/openid-configuration
    // Redirect URI som må registreres hos Signicat: {OIDC_ISSUER}/auth/bankid/callback
    'signicat' => [
        'client_id' => env('SIGNICAT_CLIENT_ID'),
        'client_secret' => env('SIGNICAT_CLIENT_SECRET'),
        'discovery_url' => env('SIGNICAT_DISCOVERY_URL'),
        'scope' => env('SIGNICAT_SCOPE', 'openid profile nin'),
        'acr_values' => env('SIGNICAT_ACR_VALUES', 'idp:nbid'),
        'redirect_uri' => env('SIGNICAT_REDIRECT_URI') ?: rtrim((string) env('OIDC_ISSUER', 'https://api.appendixholding.no'), '/') . '/auth/bankid/callback',
    ],
    // Fase 6: Laravel som OIDC-utsteder (app/Services/Oidc). issuer = iss-claim og base for /oidc/* (samme OIDC_ISSUER som portal).
    // clients: client_id → navn, hemmelighet (tom = public client med PKCE), tillatte redirect_uris.
    // Base44-utstederen godtok alle klienter; her må klient-appene registreres. Standardklienten er
    // Base44-plattformens SSO for Appendix Properties (OIDC_CLIENT_ID/OIDC_CLIENT_SECRET/OIDC_REDIRECT_URIS, kommaseparert).
    'oidc' => [
        // Beslutning 8.10.2026: ÉN utsteder for hele konsernet. Hver Base44-app pekes om via sine
        // sso_discovery_url / sso_client_id / sso_client_secret-secrets til denne. Når appene flyttes
        // til egen frontend, byttes redirect_uri til deres egen /auth/callback.
        'issuer' => env('OIDC_ISSUER', 'https://api.appendixholding.no'),
        'clients' => array_merge([
            // Base44 plattform-SSO: callback er alltid https://app.base44.com/api/apps/{appId}/auth/sso/callback
            'appendix-properties' => ['name' => 'Appendix Properties', 'secret' => env('OIDC_SECRET_PROPERTIES'),
                'redirect_uris' => 'https://app.base44.com/api/apps/692a283741b5c0d24fceeeb9/auth/sso/callback,https://aprop.no/auth/callback', 'require_pkce' => false],
            'appendix-holding' => ['name' => 'Appendix Holding', 'secret' => env('OIDC_SECRET_HOLDING'),
                'redirect_uris' => 'https://app.base44.com/api/apps/692a2988474b6d9f2ec1b7e6/auth/sso/callback', 'require_pkce' => false],
            'klinikkportal' => ['name' => 'Klinikkportal', 'secret' => env('OIDC_SECRET_KLINIKKPORTAL'),
                'redirect_uris' => 'https://app.base44.com/api/apps/691ccf20766152f6de656015/auth/sso/callback', 'require_pkce' => false],
            'kommuneoverlegene' => ['name' => 'Kommuneoverlegene', 'secret' => env('OIDC_SECRET_KOMMUNEOVERLEGENE'),
                'redirect_uris' => 'https://app.base44.com/api/apps/6924f8a869bc09d48d987563/auth/sso/callback', 'require_pkce' => false],
            'foreningsdomstolen' => ['name' => 'Foreningsdomstolen', 'secret' => env('OIDC_SECRET_FORENINGSDOMSTOLEN'),
                'redirect_uris' => 'https://app.base44.com/api/apps/6928e52262a4f288d7a6ffcc/auth/sso/callback', 'require_pkce' => false],
        ], (array) json_decode(env('OIDC_EXTRA_CLIENTS', '{}'), true)),
    ],
    // Entra ID-innlogging for faste ansatte – EGEN app-registrering (ikke graph/e-post). redirect_uri må registreres i Entra som
    // «Web»-plattform og peke på {issuer}/auth/entra/callback. Tenant kan være GUID eller verifisert domene.
    'entra_login' => [
        'tenant' => env('ENTRA_LOGIN_TENANT_ID', env('AZURE_TENANT_ID')),
        'client_id' => env('ENTRA_LOGIN_CLIENT_ID'),
        'client_secret' => env('ENTRA_LOGIN_CLIENT_SECRET'),
        'redirect_uri' => env('ENTRA_LOGIN_REDIRECT_URI') ?: rtrim(env('OIDC_ISSUER', 'https://api.appendixholding.no'), '/') . '/auth/entra/callback',
    ],
    // Min side / PortalThread: OIDC_ISSUER (base-URL for /i/<kode>, /minside, /CaseDetail) og
    // VARSEL_MOTTAKERE ("epost:mobil;epost:mobil" – ansatte som varsles ved ny henvendelse; tom = standard i PortalThread).
    'portal' => ['issuer' => env('OIDC_ISSUER', 'https://aprop.no'), 'varsel_mottakere' => env('VARSEL_MOTTAKERE')],
    // Offentlige webhook-/API-endepunkt (routes/api.php utenfor auth:sanctum):
    //  receive_locations_token: Bearer-token andre apper (Prime Leie, Medhjelp, Holding) sender til receiveLocationsData
    //                           (originalen gjenbrukte MASTER_SYNC_TOKEN – faller tilbake til samme env-variabel her).
    //  admin_status_token:      header «x-arbeidsflate-nokkel» som Arbeidsflaten sender til adminStatus (secret ARBEIDSFLATE_NOKKEL).
    //  admin_status_base_url:   base-URL for lenker i adminStatus-svaret (secret APP_BASE_URL).
    //  motta_henvendelse_origins: ekstra tillatte CORS-opphav for kontaktskjemaet (kommaseparert), i tillegg til standardlisten i MottaHenvendelse.
    'webhooks' => [
        'receive_locations_token' => env('RECEIVE_LOCATIONS_TOKEN', env('MASTER_SYNC_TOKEN')),
        'admin_status_token' => env('ARBEIDSFLATE_NOKKEL'),
        'admin_status_base_url' => env('APP_BASE_URL', 'https://aprop.no'),
        'motta_henvendelse_origins' => env('MOTTA_HENVENDELSE_ORIGINS'),
    ],
    // Brevhode-logo for exportDocumentForSigning (PDF). Standard = Base44-media-URL fra originalen; bytt til egen S3-URL.
    'letters' => ['logo_url' => env('LETTER_LOGO_URL', 'https://media.base44.com/images/public/692a283741b5c0d24fceeeb9/16d7b16e8_LogoAppendixProperties-horisontal-2026.png')],
    // Diagnose (/api/_diag). Nøkkelstatus beregnes her (når config bygges/caches) – verdiene selv lagres aldri.
    // Engangs dataflytting fra Base44 (php artisan base44:pull). Fjern BASE44_API_KEY fra .env når flyttingen er ferdig.
    'base44' => [
        'api_key' => env('BASE44_API_KEY'),
        'app_id' => env('BASE44_APP_ID', '692a283741b5c0d24fceeeb9'),
        'api_url' => env('BASE44_API_URL', 'https://app.base44.com/api'),
    ],
    'diag' => [
        'token' => env('DIAG_TOKEN'),
        'key_status' => (function () {
            $out = [];
            foreach (['AZURE_TENANT_ID', 'AZURE_CLIENT_ID', 'AZURE_CLIENT_SECRET', 'ENTRA_LOGIN_CLIENT_ID', 'ENTRA_LOGIN_CLIENT_SECRET',
                'SVEVE_USERNAME', 'SVEVE_PASSWORD', 'ANTHROPIC_API_KEY', 'GOOGLE_MAPS_API_KEY', 'MAIL_USERNAME', 'MAIL_PASSWORD',
                'SIGNICAT_CLIENT_ID', 'SIGNICAT_CLIENT_SECRET', 'SIGNICAT_DISCOVERY_URL', 'ONEDRIVE_USER_EMAIL', 'BACKUP_ENCRYPTION_KEY',
                'RECEIVE_LOCATIONS_TOKEN', 'ARBEIDSFLATE_NOKKEL', 'OIDC_ISSUER'] as $k) {
                $v = (string) env($k, '');
                $out[$k] = $v === '' ? 'mangler' : (preg_match('/\\[20[01]~|\\s/', $v) ? 'SATT, MEN UGYLDIG (mellomrom/innlimingskoder)' : 'satt (' . strlen($v) . ' tegn)');
            }
            return $out;
        })(),
    ],
];
