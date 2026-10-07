<?php

namespace App\Functions;

use App\Models\NotificationTemplate;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\User;
use Carbon\Carbon;

/**
 * Portert fra base44/functions/checkPaymentReminders/entry.ts
 *
 * Planlagt jobb (workflow «Geilo Lodge – Servicepåminnelse», cron 0 6 * * 1 UTC – navnet er
 * misvisende, workflowen kaller checkPaymentReminders). Sender betalingspåminnelse 7, 3 og 1 dag
 * før forfall for TenantPayment med status 'venter', via aktiv NotificationTemplate
 * (type payment_reminder).
 *
 * Avvik: originalen kalte sendNotification over HTTP med videresendt Authorization-header; her
 * kalles App\Functions\SendNotification direkte. Den krever en bruker – ved planlagt kjøring
 * brukes første admin-bruker (eller en transient «scheduler»-bruker) som aktør.
 * Svar: {success, checked, reminders_sent, details} eller {message} når mal mangler.
 */
class CheckPaymentReminders extends Base44Function
{
    private const REMINDER_DAYS = [7, 3, 1];

    public function __invoke(?User $user, array $payload): array
    {
        $today = now()->startOfDay();

        $template = NotificationTemplate::base44Filter(['type' => 'payment_reminder', 'is_active' => true])->first();
        if (!$template) {
            return ['message' => 'No active payment reminder template'];
        }

        $payments = TenantPayment::base44Filter(['status' => 'venter'])->get();
        $tenants = Tenant::all()->keyBy('id');
        $properties = Property::all()->keyBy('id');

        $actor = $user ?? User::where('role', 'admin')->first() ?? new User(['email' => 'scheduler@system', 'role' => 'admin']);
        $sender = app(SendNotification::class);

        $reminders = [];
        foreach ($payments as $payment) {
            try {
                $dueDate = Carbon::parse($payment->due_date)->startOfDay();
            } catch (\Throwable) {
                continue;
            }
            // Math.floor((dueDate - today) / dag)
            $daysUntilDue = (int) floor($today->diffInDays($dueDate, false));
            if (!in_array($daysUntilDue, self::REMINDER_DAYS, true)) {
                continue;
            }

            $tenant = $tenants->get($payment->tenant_id);
            $property = $properties->get($payment->property_id);
            if (!$tenant || !$tenant->email) {
                continue;
            }

            $customData = [
                'name' => trim("{$tenant->first_name} {$tenant->last_name}"),
                'property' => $property?->name ?: 'N/A',
                'amount' => $this->nok((float) $payment->amount),
                'date' => $dueDate->format('d.m.Y'),
            ];

            try {
                $r = $sender($actor, [
                    'template_id' => $template->id,
                    'recipient_id' => $tenant->id,
                    'recipient_email' => $tenant->email,
                    'recipient_phone' => $tenant->phone,
                    'channel' => $template->channel,
                    'custom_data' => $customData,
                ]);
                $failed = collect($r['results'] ?? [])->contains(fn ($x) => ($x['status'] ?? '') === 'failed');
                $reminders[] = [
                    'tenant' => $tenant->email,
                    'payment_id' => $payment->id,
                    'days_until_due' => $daysUntilDue,
                    'status' => $failed ? 'failed' : 'sent',
                ];
            } catch (\Throwable $e) {
                $reminders[] = [
                    'tenant' => $tenant->email,
                    'payment_id' => $payment->id,
                    'days_until_due' => $daysUntilDue,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => true,
            'checked' => $payments->count(),
            'reminders_sent' => count($reminders),
            'details' => $reminders,
        ];
    }

    /** Intl.NumberFormat('nb-NO', {style:'currency', currency:'NOK'}) → «1 234,00 kr». */
    private function nok(float $amount): string
    {
        return 'kr ' . number_format($amount, 2, ',', ' ');
    }
}
