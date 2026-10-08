<?php

namespace App\Models\Concerns;

/**
 * Tilleggsfelt på OidcAuthFlow som ikke finnes i Base44-skjemaet (migrasjon 2026_10_08_100001):
 * refresh_token, refresh_expires_at, access_expires_at. Lagt i trait så den genererte modellen
 * kan regenereres uten å miste dette.
 */
trait OidcAuthFlowTokens
{
    public function initializeOidcAuthFlowTokens(): void
    {
        $this->mergeFillable(['refresh_token', 'refresh_expires_at', 'access_expires_at']);
        $this->mergeCasts(['refresh_expires_at' => 'datetime', 'access_expires_at' => 'datetime']);
    }
}
