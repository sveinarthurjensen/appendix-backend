<?php

namespace App\Functions;

use App\Mail\PlainMail;
use App\Models\InvitationLog;
use App\Models\User;
use App\Models\UserRole;
use App\Services\PortalThread;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Portert fra base44/functions/sendUserInvitation/entry.ts
 *
 * Sender velkomst-/invitasjons-e-post til en UserRole (eldre rollemodell: sekretær, styremedlem …),
 * logger i InvitationLog og setter invited_date/invited_by på rollen.
 * Svar: {success: true, message, contracts_to_sign}.
 *
 * Avvik:
 *  - Entitetene UserContract og UserContractSignature finnes ikke i Laravel-modellene; kontraktslisten
 *    i e-posten og opprettelse av signaturkrav er utelatt → contracts_to_sign = 0 (se rapport).
 *  - Innloggingslenken https://appendix-properties.base44.io → config('services.portal.issuer').
 *  - Originalen returnerte {success:false, error} med 500 ved feil → FunctionException med extra success:false.
 */
class SendUserInvitation extends Base44Function
{
    private const ROLE_LABELS = [
        'admin' => 'Administrator',
        'sekretær' => 'Sekretær',
        'styremedlem' => 'Styremedlem',
        'regnskapsfører' => 'Regnskapsfører',
        'revisor' => 'Revisor',
        'cfo' => 'CFO / Økonomisjef',
        'driftsleder' => 'Driftsleder',
        'lesetilgang' => 'Lesetilgang',
    ];

    private const ROLE_DESCRIPTIONS = [
        'admin' => 'Full tilgang til alle funksjoner i systemet.',
        'sekretær' => 'Administrasjon av bookinger, leietakere og dokumenter.',
        'styremedlem' => 'Lesetilgang til rapporter og økonomi.',
        'regnskapsfører' => 'Tilgang til regnskap, inntekter og utgifter.',
        'revisor' => 'Lesetilgang til regnskap og dokumentasjon for revisjon.',
        'cfo' => 'Full tilgang til økonomi og rapporter.',
        'driftsleder' => 'Tilgang til eiendommer, vedlikehold og bookinger.',
        'lesetilgang' => 'Kun lesetilgang til utvalgte områder.',
    ];

    public function __invoke(?User $user, array $payload): array
    {
        $user = $this->requireUser($user);

        $userRoleId = $payload['userRoleId'] ?? '';
        $userRole = UserRole::find($userRoleId);
        if (!$userRole) {
            throw new FunctionException('Brukerrolle ikke funnet', 404);
        }

        try {
            // UserContract / UserContractSignature finnes ikke – ingen kontraktsliste.
            $applicableContracts = [];

            $roleLabel = self::ROLE_LABELS[$userRole->role] ?? $userRole->role;
            $roleDesc = self::ROLE_DESCRIPTIONS[$userRole->role] ?? '';
            $e = fn ($v) => PortalThread::escapeHtml($v);
            $loginUrl = PortalThread::issuer();

            $emailBody = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">'
                . '<div style="max-width: 600px; margin: 0 auto; padding: 20px;">'
                . '<div style="text-align: center; margin-bottom: 30px;"><h1 style="color: #1e3a5f;">Velkommen til Appendix Properties</h1></div>'
                . '<p>Hei,</p>'
                . '<p>Du har blitt invitert som bruker i Appendix Properties sitt eiendomsadministrasjonssystem.</p>'
                . '<div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;">'
                . '<h2 style="color: #1e3a5f; margin-top: 0;">Din rolle: ' . $e($roleLabel) . '</h2>'
                . '<p>' . $e($roleDesc) . '</p>'
                . '</div>'
                . '<h3>Om systemet</h3>'
                . '<p>Appendix Properties er et komplett system for eiendomsadministrasjon som inkluderer:</p>'
                . '<ul>'
                . '<li>Administrasjon av korttids- og langtidsutleie</li>'
                . '<li>Booking- og kontraktshåndtering</li>'
                . '<li>Økonomi og regnskap</li>'
                . '<li>Vedlikeholdsstyring</li>'
                . '<li>Dokumenthåndtering</li>'
                . '</ul>'
                . '<div style="background: #fef3c7; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b;">'
                . '<strong>Viktig:</strong> Ved å akseptere denne invitasjonen bekrefter du at du vil overholde alle taushets- og konfidensialitetsforpliktelser knyttet til din rolle. All informasjon du får tilgang til er konfidensiell og underlagt gjeldende personvernlovgivning (GDPR) og relevant arbeidsrettslig lovgivning.'
                . '</div>'
                . '<p>Logg inn for å fullføre registreringen og signere nødvendige dokumenter:</p>'
                . '<div style="text-align: center; margin: 30px 0;">'
                . '<a href="' . $loginUrl . '" style="background: #1e3a5f; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">Logg inn og fullfør registrering</a>'
                . '</div>'
                . '<hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;">'
                . '<p style="font-size: 12px; color: #64748b;">Denne e-posten er sendt fra Appendix Properties. Hvis du ikke forventet denne invitasjonen, kan du se bort fra denne meldingen eller kontakte oss.</p>'
                . '</div></body></html>';

            Mail::to($userRole->user_email)->send(new PlainMail(
                "Velkommen til Appendix Properties - Du er invitert som {$roleLabel}",
                $emailBody
            ));

            InvitationLog::create([
                'user_role_id' => $userRoleId,
                'user_email' => $userRole->user_email,
                'sent_via' => 'email',
                'sent_date' => now(),
                'message_type' => 'invitasjon',
                'status' => 'sendt',
            ]);

            $userRole->update([
                'invited_date' => now()->toDateString(),
                'invited_by' => $user->email,
            ]);

            return [
                'success' => true,
                'message' => 'Invitasjon sendt',
                'contracts_to_sign' => count($applicableContracts),
            ];
        } catch (FunctionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Feil ved sending av invitasjon: ' . $e->getMessage());
            throw new FunctionException($e->getMessage(), 500, ['success' => false]);
        }
    }
}
