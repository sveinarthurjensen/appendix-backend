<?php

namespace App\Functions;

use App\Models\User;
use App\Services\Llm;

/**
 * Portert fra base44/functions/analyzePropertyMarket/entry.ts
 *
 * Markeds- og renteanalyse for en eiendom via LLM, pluss enkel verdi-/rentekostnadsberegning.
 * Svar: {success: true, marketData: {...}, interestRates: {...}, investmentSummary, analyzed_at}
 *
 * AVVIK: Originalen brukte InvokeLLM med add_context_from_internet=true (nettsøk). Llm-tjenesten
 * her har ikke nettsøk, så tallene kommer fra modellens egen kunnskap og kan være utdaterte.
 * Modellen bes derfor oppgi hva den vet pr. sin kunnskapsdato i feltet last_updated.
 */
class AnalyzePropertyMarket extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $this->requireUser($user);

        $municipality = $payload['municipality'] ?? '';
        $postalCode = $payload['postalCode'] ?? '';
        $propertyType = $payload['propertyType'] ?? '';
        $sizeSqm = (float) ($payload['size_sqm'] ?? 0);

        $marketDataPrompt = <<<PROMPT
Analyser boligmarkedet i Norge for følgende område:
- Kommune: {$municipality}
- Postnummer: {$postalCode}
- Eiendomstype: {$propertyType}
- Størrelse: {$sizeSqm} m²

Gi meg en strukturert analyse med:
1. Gjennomsnittlig kvadratmeterpris i området
2. Prisutvikling siste 12 måneder (prosentvis endring)
3. Sammenligning med landsgjennomsnittet
4. Markedstrender (stigende/fallende/stabilt)
5. Prognoser for neste 6-12 måneder

Bruk offisielle kilder som SSB, Eiendom Norge, Finn.no statistikk og lignende. Oppgi i last_updated hvilken dato tallene gjelder for.
PROMPT;

        $today = now()->locale('nb')->isoFormat('D.M.YYYY');
        $interestRatePrompt = <<<PROMPT
Hent gjeldende boliglånsrenter i Norge per {$today}:
1. Norges Bank styringsrente
2. Gjennomsnittlig boliglånsrente hos norske banker (flytende)
3. Gjennomsnittlig boliglånsrente hos norske banker (fast 3-5 år)
4. Utvikling siste 12 måneder
5. Prognoser fra eksperter for neste 6-12 måneder

Bruk offisielle kilder som Norges Bank, Finansportalen.no, og store banker som DNB, Nordea, Sparebank 1. Oppgi i last_updated hvilken dato tallene gjelder for.
PROMPT;

        $llm = app(Llm::class);
        try {
            // Originalen kjørte de to parallelt (Promise.all); her sekvensielt.
            $marketAnalysis = $llm->json($marketDataPrompt, [
                'type' => 'object',
                'properties' => [
                    'average_price_per_sqm' => ['type' => 'number'],
                    'price_change_12m_percent' => ['type' => 'number'],
                    'national_comparison' => ['type' => 'string'],
                    'market_trend' => ['type' => 'string'],
                    'forecast_6_12m' => ['type' => 'string'],
                    'data_sources' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'last_updated' => ['type' => 'string'],
                ],
            ]);
            $interestRateAnalysis = $llm->json($interestRatePrompt, [
                'type' => 'object',
                'properties' => [
                    'policy_rate' => ['type' => 'number'],
                    'average_floating_rate' => ['type' => 'number'],
                    'average_fixed_rate' => ['type' => 'number'],
                    'rate_change_12m_percent' => ['type' => 'number'],
                    'forecast_6_12m' => ['type' => 'string'],
                    'data_sources' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'last_updated' => ['type' => 'string'],
                ],
            ]);

            $pricePerSqm = (float) ($marketAnalysis['average_price_per_sqm'] ?? 0);
            $floating = (float) ($interestRateAnalysis['average_floating_rate'] ?? 0);

            $estimatedValue = $sizeSqm * $pricePerSqm;
            $loanAmount = $estimatedValue * 0.85; // 85 % belåning
            $monthlyInterestCost = ($loanAmount * $floating / 100) / 12;

            $summaryPrompt = <<<PROMPT
Basert på følgende data:

Boligmarked:
- Område: {$municipality}, {$postalCode}
- Gjennomsnittspris per m²: {$pricePerSqm} kr
- Prisutvikling 12 mnd: {$this->s($marketAnalysis['price_change_12m_percent'] ?? null)}%
- Markedstrend: {$this->s($marketAnalysis['market_trend'] ?? null)}

Renter:
- Styringsrente: {$this->s($interestRateAnalysis['policy_rate'] ?? null)}%
- Boliglånsrente flytende: {$floating}%
- Renteutvikling 12 mnd: {$this->s($interestRateAnalysis['rate_change_12m_percent'] ?? null)}%

Lag en kort investeringsanalyse (2-3 setninger) som forklarer sammenhengen mellom boligpriser og renter,
og gi en anbefaling for potensielle investorer. Vær konkret og nøytral.
PROMPT;

            $investmentSummary = $llm->text($summaryPrompt);
        } catch (\Throwable $e) {
            report($e);
            throw new FunctionException($e->getMessage(), 500, ['success' => false]);
        }

        return [
            'success' => true,
            'marketData' => array_merge($marketAnalysis, [
                'estimated_property_value' => (int) round($estimatedValue),
                'estimated_loan_amount' => (int) round($loanAmount),
                'monthly_interest_cost' => (int) round($monthlyInterestCost),
            ]),
            'interestRates' => $interestRateAnalysis,
            'investmentSummary' => $investmentSummary,
            'analyzed_at' => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
        ];
    }

    private function s(mixed $v): string
    {
        return is_scalar($v) ? (string) $v : 'undefined';
    }
}
