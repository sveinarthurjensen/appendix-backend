<?php

namespace App\Providers;

use App\Models\Contract;
use App\Observers\ContractObserver;
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
        Contract::observe(ContractObserver::class);
    }
}
