<?php

namespace App\Functions;

use App\Models\Booking;
use App\Models\Contract;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\User;

/**
 * Portert fra base44/functions/getTenantPortalData/entry.ts
 *
 * Tenant-portal: løser opp callerens Tenant via e-post og returnerer KUN dennes
 * egne kontrakter, betalinger og bookinger (owner-scoping per tenant).
 */
class GetTenantPortalData extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $actor = $this->requireUser($user);
        if (!$actor->email) {
            throw new FunctionException('Brukeren mangler e-post', 400);
        }

        $tenant = Tenant::base44Filter(['email' => $actor->email])->first();
        if (!$tenant) {
            return ['tenant' => null, 'contracts' => [], 'payments' => [], 'bookings' => []];
        }

        return [
            'tenant' => $tenant->toBase44Array(),
            'contracts' => Contract::base44Filter(['tenant_id' => $tenant->id])->get()->map->toBase44Array()->values()->all(),
            'payments' => TenantPayment::base44Filter(['tenant_id' => $tenant->id])->get()->map->toBase44Array()->values()->all(),
            'bookings' => Booking::base44Filter(['tenant_id' => $tenant->id])->get()->map->toBase44Array()->values()->all(),
        ];
    }
}
