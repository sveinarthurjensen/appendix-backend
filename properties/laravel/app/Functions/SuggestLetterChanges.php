<?php

namespace App\Functions;

use App\Models\User;
use App\Services\Llm;

/**
 * Portert fra base44/functions/suggestLetterChanges/entry.ts
 *
 * Admin får konkrete endringsforslag til et brevutkast basert på nye dokumenter.
 * Svar: {suggestions: [{area, current, proposed, reason}], revised_body, summary}
 *
 * AVVIK: Originalen brukte model 'gpt_5_4' hos Base44; her brukes services.anthropic.model.
 */
class SuggestLetterChanges extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $this->requireUser($user);
        if (!$user->hasRole('admin')) {
            throw new FunctionException('Kun for administratorer', 403);
        }

        $letterSubject = $payload['letter_subject'] ?? '';
        $letterBody = $payload['letter_body'] ?? '';
        $newDocuments = $payload['new_documents'] ?? [];
        $instruction = $payload['instruction'] ?? null;

        $docsText = implode("\n\n", array_map(
            fn ($d, $i) => 'Dokument ' . ($i + 1) . ': ' . ($d['name'] ?? '') . "\n" . ($d['content_text'] ?? $d['description'] ?? ''),
            $newDocuments,
            array_keys($newDocuments)
        ));

        $prompt = 'Du er saksbehandlingsassistent for eiendomsforvaltning. Analyser det eksisterende brevutkastet mot nye dokumenter i saken, og foreslå konkrete endringer.'
            . "\n\nEksisterende brev:"
            . "\nEmne: " . $letterSubject
            . "\nInnhold: " . $letterBody
            . "\n\nNye dokumenter i saken:\n" . ($docsText ?: 'Ingen nye dokumenter oppgitt.')
            . "\n\nInstruksjon: " . ($instruction ?: 'Foreslå forbedringer og justeringer av brevet basert på de nye dokumentene, slik at brevet blir mest mulig presist og oppdatert.')
            . "\n\nReturner JSON med:"
            . "\n- \"suggestions\": array av objekter {area, current, proposed, reason} med konkrete endringsforslag"
            . "\n- \"revised_body\": revidert fullstendig brevtekst som HTML (<p>-tagger)"
            . "\n- \"summary\": kort oppsummering (1-2 setninger) av foreslåtte endringer";

        try {
            $result = app(Llm::class)->json($prompt, [
                'type' => 'object',
                'properties' => [
                    'suggestions' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'area' => ['type' => 'string'],
                                'current' => ['type' => 'string'],
                                'proposed' => ['type' => 'string'],
                                'reason' => ['type' => 'string'],
                            ],
                        ],
                    ],
                    'revised_body' => ['type' => 'string'],
                    'summary' => ['type' => 'string'],
                ],
            ], 8000);
        } catch (\Throwable $e) {
            report($e);
            throw new FunctionException($e->getMessage(), 500);
        }

        // Originalen returnerte LLM-objektet uendret
        return $result;
    }
}
