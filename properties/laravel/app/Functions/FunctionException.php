<?php

namespace App\Functions;

/** Kastes fra funksjoner for å returnere en feil med HTTP-status, slik Deno-versjonene gjorde med Response.json({error}, {status}). */
class FunctionException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 400, public readonly array $extra = [])
    {
        parent::__construct($message);
    }
}
