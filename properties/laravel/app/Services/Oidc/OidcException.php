<?php

namespace App\Services\Oidc;

/** OAuth2/OIDC-feil: $error er feilkoden klienten får ({"error": "...", "error_description": "..."}). */
class OidcException extends \RuntimeException
{
    public function __construct(public readonly string $error, string $description = '', public readonly int $status = 400)
    {
        parent::__construct($description ?: $error);
    }

    public function toArray(): array
    {
        return ['error' => $this->error, 'error_description' => $this->getMessage()];
    }
}
