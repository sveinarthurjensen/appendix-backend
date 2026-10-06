<?php
// Legges inn i config/services.php av install-properties.sh (nøkkelen 'appendix' merges inn)
return [
    'functions' => ['url' => env('FUNCTIONS_URL'), 'token' => env('FUNCTIONS_TOKEN')],
    'sveve' => ['username' => env('SVEVE_USERNAME'), 'password' => env('SVEVE_PASSWORD')],
    'anthropic' => ['key' => env('ANTHROPIC_API_KEY'), 'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5')],
    'google_maps' => ['key' => env('GOOGLE_MAPS_API_KEY')],
    'brreg' => ['base' => env('BRREG_BASE', 'https://data.brreg.no/enhetsregisteret/api')],
    'webhook' => ['url' => env('WEBHOOK_URL')],
    'graph' => ['tenant' => env('AZURE_TENANT_ID'), 'client_id' => env('AZURE_CLIENT_ID'), 'client_secret' => env('AZURE_CLIENT_SECRET')],
    'signicat' => ['client_id' => env('SIGNICAT_CLIENT_ID'), 'client_secret' => env('SIGNICAT_CLIENT_SECRET')],
    // Min side / PortalThread: OIDC_ISSUER (base-URL for /i/<kode>, /minside, /CaseDetail) og
    // VARSEL_MOTTAKERE ("epost:mobil;epost:mobil" – ansatte som varsles ved ny henvendelse; tom = standard i PortalThread).
    'portal' => ['issuer' => env('OIDC_ISSUER', 'https://aprop.no'), 'varsel_mottakere' => env('VARSEL_MOTTAKERE')],
    // Brevhode-logo for exportDocumentForSigning (PDF). Standard = Base44-media-URL fra originalen; bytt til egen S3-URL.
    'letters' => ['logo_url' => env('LETTER_LOGO_URL', 'https://media.base44.com/images/public/692a283741b5c0d24fceeeb9/16d7b16e8_LogoAppendixProperties-horisontal-2026.png')],
];
