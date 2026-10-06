<?php

namespace App\Functions;

use App\Models\User;
use App\Services\Llm;

/**
 * Portert fra base44/functions/generateAiLetterResponse/entry.ts
 *
 * Admin får generert et formelt svarbrev (emne + HTML-brødtekst) fra sakskontekst,
 * dokumenter og instruksjon. Svar: {subject, body}
 *
 * AVVIK: Originalen brukte model 'gpt_5_4' hos Base44; her brukes services.anthropic.model.
 */
class GenerateAiLetterResponse extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $this->requireUser($user);
        if (!$user->hasRole('admin')) {
            throw new FunctionException('Kun for administratorer', 403);
        }

        $ctx = $payload['case_context'] ?? [];
        $documents = $payload['documents'] ?? [];
        $recipient = $payload['recipient'] ?? null;
        $instruction = $payload['instruction'] ?? null;
        $tone = $payload['tone'] ?? null;
        $existingBody = $payload['existing_body'] ?? null;

        $docsText = implode("\n\n", array_map(
            fn ($d, $i) => 'Dokument ' . ($i + 1) . ': ' . ($d['name'] ?? '') . "\n" . ($d['content_text'] ?? $d['description'] ?? ''),
            $documents,
            array_keys($documents)
        ));

        $prompt = 'Du er en saksbehandlingsassistent for eiendomsforvaltning (Appendix Properties). Skriv et formelt brev på norsk som svar til mottakeren.'
            . "\n\nKontekst om saken:"
            . "\nTittel: " . ($ctx['title'] ?? '')
            . "\nSakstype: " . ($ctx['case_type'] ?? '')
            . "\nBeskrivelse: " . ($ctx['description'] ?? '')
            . "\nMotpart: " . ($ctx['counterpart_name'] ?? '')
            . "\nMotpart adresse: " . ($ctx['counterpart_address'] ?? '')
            . "\n\nMottaker av brevet: " . ($recipient ?: ($ctx['counterpart_name'] ?? ''))
            . "\nTone: " . ($tone ?: 'formell, profesjonell og presis')
            . "\n\nInstruksjon fra saksbehandler:\n" . ($instruction ?: 'Skriv et balansert, saklig svar som ivaretar våre interesser.')
            . "\n\n" . ($docsText ? "Relevante dokumenter i saken:\n" . $docsText : '')
            . "\n\n" . ($existingBody ? "Eksisterende utkast (for referanse, kan videreutvikles):\n" . $existingBody : '')
            . "\n\nKrav:"
            . "\n- Skriv et komplett, sendingsklart formelt brev på norsk."
            . "\n- Start med en kort innledning som viser til saken."
            . "\n- Vær saklig, presis og ikke aggressive."
            . "\n- Avslutt med tydelig konklusjon/videre håndtering."
            . "\n- Returner KUN JSON med feltene \"subject\" (emne, kort) og \"body\" (brødtekst som HTML med <p>-tagger, ingen <html> eller <body>).";

        try {
            $result = app(Llm::class)->json($prompt, [
                'type' => 'object',
                'properties' => [
                    'subject' => ['type' => 'string'],
                    'body' => ['type' => 'string'],
                ],
            ], 8000);
        } catch (\Throwable $e) {
            report($e);
            throw new FunctionException($e->getMessage(), 500);
        }

        return [
            'subject' => $result['subject'] ?? '',
            'body' => $result['body'] ?? '',
        ];
    }
}
