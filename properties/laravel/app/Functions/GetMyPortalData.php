<?php

namespace App\Functions;

use App\Models\Booking;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\PortalMessage;
use App\Models\Property;
use App\Models\PropertyInfo;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\User;

/**
 * Portert fra base44/functions/getMyPortalData/entry.ts
 *
 * Ekstern «Min Side»-data for gjester (role='guest') og leietakere (role='user').
 * OWNER-SCOPING (kun actor.id / actor.email fra innlogget sesjon – ingen brukerstyrt parameter):
 *   - Bookinger:       tenant_id == actor.id
 *   - Kontrakter:      tenant_id == actor.id ELLER tenant_id == tenant.id (via e-post)
 *   - TenantPayment:   tenant_id == tenant.id
 *   - Payment (gjest): tenant_id == actor.id
 *   - PortalMessage:   user_email == actor.email
 */
class GetMyPortalData extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $actor = $this->requireUser($user);
        if (!$actor->id || !$actor->email) {
            throw new FunctionException('Bruker mangler id/e-post', 400);
        }

        // 1) Tenant-post (langtidsleietaker) via e-post.
        $tenant = Tenant::base44Filter(['email' => $actor->email])->first();

        // 2) Kontrakter bundet direkte til brukeren.
        $ownerContracts = Contract::base44Filter(['tenant_id' => $actor->id])->get();

        // 3) Kontrakter + betalinger bundet til Tenant-posten.
        $tenantContracts = collect();
        $tenantPayments = collect();
        if ($tenant) {
            $tenantContracts = Contract::base44Filter(['tenant_id' => $tenant->id])->get();
            $tenantPayments = TenantPayment::base44Filter(['tenant_id' => $tenant->id])->get();
        }

        // 4) Bookinger og gjeste-betalinger.
        $bookings = Booking::base44Filter(['tenant_id' => $actor->id])->get();
        $payments = Payment::base44Filter(['tenant_id' => $actor->id])->get();

        // 5) Slå sammen kontrakter, dedup på id.
        $contracts = $ownerContracts->concat($tenantContracts)->keyBy('id')->values();

        // 6) Meldingstråder, gruppert per case_id.
        $threads = [];
        foreach (PortalMessage::base44Filter(['user_email' => $actor->email])->get() as $m) {
            $threads[$m->case_id] ??= [
                'case_id' => $m->case_id,
                'case_number' => $m->case_number,
                'subject' => $m->subject,
                'messages' => [],
            ];
            $threads[$m->case_id]['messages'][] = [
                'id' => $m->id,
                'direction' => $m->direction,
                'message' => $m->message,
                'channel' => $m->channel,
                'sender_name' => $m->sender_name,
                'created_date' => $m->created_date,
            ];
        }
        $threads = array_values($threads);

        // 7) Eiendommer referert av bookinger/kontrakter (kun visningsfelt).
        $propertyIds = $bookings->pluck('property_id')->concat($contracts->pluck('property_id'))->filter()->unique();
        $allProperties = Property::all();
        $properties = [];
        foreach ($allProperties as $prop) {
            if ($propertyIds->contains($prop->id)) {
                $properties[$prop->id] = [
                    'id' => $prop->id,
                    'name' => $prop->name,
                    'address' => $prop->address,
                    'city' => $prop->city,
                    'main_image' => $prop->main_image,
                    'rental_mode' => $prop->rental_mode,
                ];
            }
        }

        // 8) Oppholdsinfo for bekreftede/innsjekkede korttidsopphold som ikke er avsluttet.
        //    Dørkode/nøkkel/WiFi-passord vises fra dagen før innsjekk t.o.m. utsjekkdagen.
        $todayStr = now()->utc()->format('Y-m-d');
        $dayBefore = fn (string $d) => \Carbon\Carbon::parse(substr($d, 0, 10) . 'T00:00:00Z')->subDay()->format('Y-m-d');
        $dateStr = fn ($d) => $d ? substr((string) (is_object($d) ? $d->format('Y-m-d') : $d), 0, 10) : null;

        $activeStays = $bookings->filter(fn ($b) =>
            in_array($b->status, ['bekreftet', 'innsjekket'], true) && $dateStr($b->end_date) && $dateStr($b->end_date) >= $todayStr
        );
        $stayInfo = [];
        if ($activeStays->isNotEmpty()) {
            $stayPropIds = $activeStays->pluck('property_id')->filter()->unique()->values();
            $infoByProp = [];
            foreach ($stayPropIds as $pid) {
                $infoByProp[$pid] = PropertyInfo::base44Filter(['property_id' => $pid])->first();
            }
            $propById = $allProperties->keyBy('id');

            foreach ($activeStays as $b) {
                $info = $infoByProp[$b->property_id] ?? null;
                $prop = $propById[$b->property_id] ?? null;
                $start = $dateStr($b->start_date);
                $end = $dateStr($b->end_date);
                $accessOpen = $start && $todayStr >= $dayBefore($start) && $todayStr <= $end;
                $stayInfo[$b->id] = [
                    'check_in_time' => $prop?->check_in_time ?: null,
                    'check_out_time' => $prop?->check_out_time ?: null,
                    'house_rules' => $info?->house_rules ?: ($prop?->rules ?: null),
                    'check_in_instructions' => $info?->check_in_instructions ?: null,
                    'check_out_instructions' => $info?->check_out_instructions ?: null,
                    'parking_info' => $info?->parking_info ?: null,
                    'trash_info' => $info?->trash_info ?: null,
                    'heating_info' => $info?->heating_info ?: null,
                    'appliances_guide' => $info?->appliances_guide ?: null,
                    'emergency_contacts' => $info?->emergency_contacts ?: [],
                    'nearby_places' => $info?->nearby_places ?: [],
                    'custom_info' => $info?->custom_info ?: null,
                    'access_open' => $accessOpen,
                    'access_opens_on' => $start ? $dayBefore($start) : null,
                    'wifi_name' => $info?->wifi_name ?: null,
                    'wifi_password' => $accessOpen ? ($info?->wifi_password ?: null) : null,
                    'door_code' => $accessOpen ? ($info?->door_code ?: null) : null,
                    'key_location' => $accessOpen ? ($info?->key_location ?: null) : null,
                ];
            }
        }

        return [
            'stayInfo' => (object) $stayInfo,
            'profile' => [
                'id' => $actor->id,
                'email' => $actor->email,
                'full_name' => $actor->full_name,
                'role' => $actor->role,
                'mobile' => $actor->mobile ?? null,
                'account_number' => $actor->account_number ?? null,
                'bankid_verified' => (bool) $actor->bankid_verified,
            ],
            'tenant' => $tenant?->toBase44Array(),
            'bookings' => $bookings->map->toBase44Array()->values()->all(),
            'contracts' => $contracts->map->toBase44Array()->values()->all(),
            'tenantPayments' => $tenantPayments->map->toBase44Array()->values()->all(),
            'payments' => $payments->map->toBase44Array()->values()->all(),
            'properties' => (object) $properties,
            'threads' => $threads,
        ];
    }
}
