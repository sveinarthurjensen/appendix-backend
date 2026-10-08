<?php

namespace App\Providers;

use App\Models\Contract;
use App\Observers\ContractObserver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

/**
 * Registrerer Eloquent-observere som erstatter Base44 entity-workflows
 * (base44/workflows/Contract status guard (update).jsonc → ContractObserver).
 *
 * Må registreres i bootstrap/providers.php (Laravel 11+):
 *   return [
 *       App\Providers\AppServiceProvider::class,
 *       App\Providers\FunctionsServiceProvider::class,
 *   ];
 */
class FunctionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        // Laravel står bak Caddy (TLS) → nginx → php-fpm og ser selv bare http.
        // Tving https i alle genererte URL-er (OIDC-redirects, discovery) når APP_URL er https.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
            URL::forceRootUrl(config('app.url'));
        }

        Contract::observe(ContractObserver::class);
    }
}
