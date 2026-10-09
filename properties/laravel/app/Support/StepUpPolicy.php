<?php

namespace App\Support;

/**
 * Sentral tabell for step-up per funksjon: navn → [nivå, maks alder i minutter].
 * Nivå: 'bankid' (kun BankID) eller 'strong' (BankID eller Entra med MFA). Håndheves i FunctionController::invoke,
 * dvs. i backend – ikke bare i frontend. Utvid tabellen når flere funksjoner/apper flyttes.
 * Tidsgrensene følger de som ble kartlagt i Base44-appene 9.10.2026.
 */
class StepUpPolicy
{
    public const FUNCTIONS = [
        // Passordhvelv: tidligere bare et sessionStorage-flagg i nettleseren
        'vaultManager' => ['strong', 10],
        // Signering/eksport av dokumenter
        'exportDocumentForSigning' => ['strong', 30],
        // Regnskap/økonomi (Holding) når funksjonene flyttes
        'getRegnskapDatasett' => ['strong', 30],
        'manageBilag' => ['strong', 30],
        'approveBilag' => ['strong', 30],
        'deleteBilag' => ['strong', 10],
        'pushToUniEconomy' => ['strong', 10],
        'unimicroSendFaktura' => ['strong', 10],
        // Helsedata (Klinikkportal/Kommuneoverlegene) når de flyttes: fersk BankID
        'respondToOffer' => ['bankid', 30],
        'submitHealthDeclaration' => ['bankid', 30],
        'signEquipmentLoan' => ['bankid', 30],
        'signDocumentBankId' => ['bankid', 10],
        'signContractBankId' => ['bankid', 10],
        'meldPaatale' => ['bankid', 30],
    ];

    public static function for(string $function): ?array
    {
        $key = lcfirst($function);
        return self::FUNCTIONS[$key] ?? null;
    }
}
