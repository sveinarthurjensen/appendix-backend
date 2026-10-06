<?php
// Legges inn i config/services.php av install-properties.sh (nøkkelen 'appendix' merges inn)
return [
    'functions' => ['url' => env('FUNCTIONS_URL'), 'token' => env('FUNCTIONS_TOKEN')],
    'sveve' => ['username' => env('SVEVE_USERNAME'), 'password' => env('SVEVE_PASSWORD')],
    'anthropic' => ['key' => env('ANTHROPIC_API_KEY'), 'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5')],
    'google_maps' => ['key' => env('GOOGLE_MAPS_API_KEY')],
    'brreg' => ['base' => env('BRREG_BASE', 'https://data.brreg.no/enhetsregisteret/api')],
    'graph' => ['tenant' => env('AZURE_TENANT_ID'), 'client_id' => env('AZURE_CLIENT_ID'), 'client_secret' => env('AZURE_CLIENT_SECRET')],
    'signicat' => ['client_id' => env('SIGNICAT_CLIENT_ID'), 'client_secret' => env('SIGNICAT_CLIENT_SECRET')],
];
