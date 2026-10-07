<?php
// Legges inn i config/services.php av install-properties.sh (nøkkelen 'appendix' merges inn)
return [
    'functions' => ['url' => env('FUNCTIONS_URL'), 'token' => env('FUNCTIONS_TOKEN')],
    'sveve' => ['username' => env('SVEVE_USERNAME'), 'password' => env('SVEVE_PASSWORD')],
    'anthropic' => ['key' => env('ANTHROPIC_API_KEY'), 'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5')],
    'google_maps' => ['key' => env('GOOGLE_MAPS_API_KEY')],
    'brreg' => ['base' => env('BRREG_BASE', 'https://data.brreg.no/enhetsregisteret/api')],
    'webhook' => ['url' => env('WEBHOOK_URL')],
    // onedrive_user: brukeren hvis OneDrive backupen lastes opp til (azureOneDriveBackup). Krever Files.ReadWrite.All (application).
    'graph' => ['tenant' => env('AZURE_TENANT_ID'), 'client_id' => env('AZURE_CLIENT_ID'), 'client_secret' => env('AZURE_CLIENT_SECRET'), 'onedrive_user' => env('ONEDRIVE_USER_EMAIL')],
    // azureOneDriveBackup: katalogen pgbackup-containeren skriver pg_dump-filer til (montert read-only i app/scheduler), og målmappe i OneDrive.
    'backup' => ['dir' => env('BACKUP_DIR', '/backups'), 'onedrive_folder' => env('ONEDRIVE_BACKUP_FOLDER', 'Backups/AppendixProperties')],
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
    'reminders' => ['admin_email' => env('REMINDER_ADMIN_EMAIL', 'svein.arthur.jensen@gmail.com'), 'admin_phone' => env('REMINDER_ADMIN_PHONE', '+4790620833')],
    // runSecurityAudit: flere admin-brukere enn dette gir warning.
    'security' => ['max_admins' => (int) env('SECURITY_MAX_ADMINS', 5)],
    'signicat' => ['client_id' => env('SIGNICAT_CLIENT_ID'), 'client_secret' => env('SIGNICAT_CLIENT_SECRET')],
    // Min side / PortalThread: OIDC_ISSUER (base-URL for /i/<kode>, /minside, /CaseDetail) og
    // VARSEL_MOTTAKERE ("epost:mobil;epost:mobil" – ansatte som varsles ved ny henvendelse; tom = standard i PortalThread).
    'portal' => ['issuer' => env('OIDC_ISSUER', 'https://aprop.no'), 'varsel_mottakere' => env('VARSEL_MOTTAKERE')],
    // Brevhode-logo for exportDocumentForSigning (PDF). Standard = Base44-media-URL fra originalen; bytt til egen S3-URL.
    'letters' => ['logo_url' => env('LETTER_LOGO_URL', 'https://media.base44.com/images/public/692a283741b5c0d24fceeeb9/16d7b16e8_LogoAppendixProperties-horisontal-2026.png')],
];
