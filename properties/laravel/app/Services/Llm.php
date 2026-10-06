<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Erstatter integrations.Core.InvokeLLM. Bruker Anthropic Messages API.
 * Konfig: services.anthropic.key, services.anthropic.model
 */
class Llm
{
    public function text(string $prompt, int $maxTokens = 4000): string
    {
        $r = $this->call($prompt, $maxTokens);
        return $r['content'][0]['text'] ?? '';
    }

    /** Ber om JSON etter gitt skjema og returnerer dekodet array (tom array ved feil). */
    public function json(string $prompt, array $schema, int $maxTokens = 4000): array
    {
        $full = $prompt . "\n\nSvar KUN med gyldig JSON som følger dette skjemaet, uten forklaring og uten kodeblokk:\n" . json_encode($schema, JSON_UNESCAPED_UNICODE);
        $txt = $this->text($full, $maxTokens);
        $txt = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($txt));
        $decoded = json_decode($txt, true);
        if (!is_array($decoded) && preg_match('/\{.*\}/s', $txt, $m)) {
            $decoded = json_decode($m[0], true);
        }
        return is_array($decoded) ? $decoded : [];
    }

    private function call(string $prompt, int $maxTokens): array
    {
        $key = config('services.anthropic.key') ?: throw new \RuntimeException('ANTHROPIC_API_KEY mangler');
        return Http::withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
            ->timeout(120)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.anthropic.model', 'claude-sonnet-4-5'),
                'max_tokens' => $maxTokens,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ])->throw()->json();
    }
}
