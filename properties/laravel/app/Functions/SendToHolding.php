<?php

namespace App\Functions;

use App\Models\Booking;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Portert fra base44/functions/sendToHolding/entry.ts
 *
 * Planlagt jobb (workflow «Send data til Appendix Holding», hvert 30. minutt): bygger full
 * økonomirapport (eiendommer, priser, leie, bookinger, betalinger) og POST-er til Appendix
 * Holding-appen – både receiveLocationsData og receiveExternalData.
 *
 * URL-ene ligger i config services.holding.locations_url / external_data_url (standard = Base44-
 * funksjonene i Holding-appen 692a2988474b6d9f2ec1b7e6) og byttes når Holding-appen flyttes.
 * Svar: {success, message, locations_sent, timestamp, locations_result, external_data_result}
 */
class SendToHolding extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $locationsUrl = config('services.holding.locations_url');
        $externalDataUrl = config('services.holding.external_data_url');
        $companyId = config('services.holding.company_id');
        if (!$locationsUrl || !$externalDataUrl) {
            throw new FunctionException('Holding ikke konfigurert (HOLDING_LOCATIONS_URL / HOLDING_EXTERNAL_DATA_URL)', 500);
        }

        $properties = Property::all();
        $bookings = Booking::all();
        $tenants = Tenant::all();
        $contracts = Contract::all();
        $incomes = Income::all();
        $expenses = Expense::all();
        $payments = Payment::all();
        $invoices = Invoice::all();

        $now = now();
        $currentYear = (int) $now->year;
        $currentMonth = (int) $now->month;
        $sum = fn ($coll, string $field) => (float) $coll->sum(fn ($x) => (float) ($x->{$field} ?: 0));

        // --- Inntekter og utgifter ---
        $yearIncomes = $incomes->filter(fn ($i) => (int) $i->year === $currentYear);
        $yearExpenses = $expenses->filter(fn ($e) => (int) $e->year === $currentYear);
        $monthIncomes = $yearIncomes->filter(fn ($i) => (int) $i->month === $currentMonth);
        $monthExpenses = $yearExpenses->filter(fn ($e) => (int) $e->month === $currentMonth);

        $totalIncomeYear = $sum($yearIncomes, 'amount');
        $totalExpensesYear = $sum($yearExpenses, 'amount');
        $totalIncomeMonth = $sum($monthIncomes, 'amount');
        $totalExpensesMonth = $sum($monthExpenses, 'amount');

        // --- Betalinger ---
        $paidPayments = $payments->where('status', 'betalt');
        $pendingPayments = $payments->whereIn('status', ['venter', 'forfalt']);
        $overduePayments = $payments->where('status', 'forfalt');
        $totalPaid = $sum($paidPayments, 'amount');
        $totalPending = $sum($pendingPayments, 'amount');

        // --- Kontrakter ---
        $activeContracts = $contracts->where('status', 'aktiv');
        $totalMonthlyRent = $sum($activeContracts, 'monthly_rent');
        $totalDeposits = $sum($activeContracts, 'deposit_amount');

        // --- Bookinger ---
        $activeBookings = $bookings->whereIn('status', ['bekreftet', 'innsjekket']);
        $totalBookingRevenue = $sum($bookings->where('status', '!=', 'kansellert'), 'total_price');

        // --- Fakturaer ---
        $unpaidInvoices = $invoices->whereIn('status', ['sendt', 'forfalt']);
        $totalUnpaidInvoices = $sum($unpaidInvoices, 'amount');

        // --- Per eiendom ---
        $propertyDetails = $properties->map(function ($p) use ($activeContracts, $bookings, $yearIncomes, $yearExpenses, $payments, $now, $sum) {
            $propContracts = $activeContracts->where('property_id', $p->id);
            $propBookings = $bookings->filter(fn ($b) => $b->property_id === $p->id && $b->status !== 'kansellert');
            $propIncomes = $yearIncomes->where('property_id', $p->id);
            $propExpenses = $yearExpenses->where('property_id', $p->id);
            $propPayments = $payments->where('property_id', $p->id);

            $hasActiveContract = $propContracts->isNotEmpty();
            $hasActiveBooking = $bookings->contains(function ($b) use ($p, $now) {
                if ($b->property_id !== $p->id || !in_array($b->status, ['innsjekket', 'bekreftet'], true)) {
                    return false;
                }
                try {
                    return \Carbon\Carbon::parse($b->start_date)->lte($now) && \Carbon\Carbon::parse($b->end_date)->gte($now);
                } catch (\Throwable) {
                    return false;
                }
            });
            $rentalStatus = $p->rental_mode === 'langtid'
                ? ($hasActiveContract ? 'utleid' : 'ledig')
                : ($hasActiveBooking ? 'utleid' : 'ledig');

            $incomeYear = $sum($propIncomes, 'amount');
            $expensesYear = $sum($propExpenses, 'amount');

            return [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->type,
                'rental_mode' => $p->rental_mode,
                'rental_type' => $p->rental_type ?: 'privat',
                'tax_classification' => $p->tax_classification,
                'status' => $p->status,
                'rental_status' => $rentalStatus,
                'city' => $p->city,
                'address' => $p->address,
                'size_sqm' => $p->size_sqm,
                'bedrooms' => $p->bedrooms,
                'price_per_night' => $p->price_per_night,
                'price_per_night_weekend' => $p->price_per_night_weekend,
                'cleaning_fee' => $p->cleaning_fee,
                'price_per_month' => $p->price_per_month,
                'deposit_amount' => $p->deposit_amount,
                'purchase_price' => $p->purchase_price,
                'purchase_date' => $p->purchase_date,
                'loan_amount' => $p->loan_amount,
                'loan_interest_rate' => $p->loan_interest_rate,
                'annual_property_tax' => $p->annual_property_tax,
                'income_year' => $incomeYear,
                'expenses_year' => $expensesYear,
                'profit_year' => $incomeYear - $expensesYear,
                'active_contracts' => $propContracts->count(),
                'monthly_rent' => $sum($propContracts, 'monthly_rent'),
                'total_bookings' => $propBookings->count(),
                'booking_revenue' => $sum($propBookings, 'total_price'),
                'payments_paid' => $sum($propPayments->where('status', 'betalt'), 'amount'),
                'payments_pending' => $sum($propPayments->whereIn('status', ['venter', 'forfalt']), 'amount'),
            ];
        })->values();

        $locations = $propertyDetails->map(fn ($p) => [
            'name' => $p['name'],
            'type' => 'eiendom',
            'street' => $p['address'] ?: '',
            'city' => $p['city'] ?: '',
            'area_sqm' => $p['size_sqm'] ?: null,
            'status' => $p['status'] === 'aktiv' ? 'aktiv' : 'inaktiv',
            'external_id' => $p['id'],
            'description' => "{$p['type']} - {$p['rental_mode']}",
            'rental_status' => $p['rental_status'],
            'economics' => [
                'rental_mode' => $p['rental_mode'],
                'rental_type' => $p['rental_type'],
                'tax_classification' => $p['tax_classification'],
                'purchase_price' => $p['purchase_price'],
                'purchase_date' => $p['purchase_date'],
                'loan_amount' => $p['loan_amount'],
                'loan_interest_rate' => $p['loan_interest_rate'],
                'annual_property_tax' => $p['annual_property_tax'],
                'price_per_night' => $p['price_per_night'],
                'price_per_night_weekend' => $p['price_per_night_weekend'],
                'cleaning_fee' => $p['cleaning_fee'],
                'price_per_month' => $p['price_per_month'],
                'deposit_amount' => $p['deposit_amount'],
                'income_year' => $p['income_year'],
                'expenses_year' => $p['expenses_year'],
                'profit_year' => $p['profit_year'],
                'monthly_rent' => $p['monthly_rent'],
                'rental_status' => $p['rental_status'],
                'active_contracts' => $p['active_contracts'],
                'total_bookings' => $p['total_bookings'],
                'booking_revenue' => $p['booking_revenue'],
                'payments_paid' => $p['payments_paid'],
                'payments_pending' => $p['payments_pending'],
            ],
        ])->values()->all();

        $period = sprintf('%d-%02d', $currentYear, $currentMonth);
        $activeProps = $properties->where('status', 'aktiv')->count();
        $activeTenants = $tenants->where('status', 'aktiv')->count();

        $consolidated = [
            'period' => $period,
            'revenue_year' => $totalIncomeYear,
            'expenses_year' => $totalExpensesYear,
            'profit_year' => $totalIncomeYear - $totalExpensesYear,
            'revenue_month' => $totalIncomeMonth,
            'expenses_month' => $totalExpensesMonth,
            'profit_month' => $totalIncomeMonth - $totalExpensesMonth,
            'monthly_rent_total' => $totalMonthlyRent,
            'deposits_held' => $totalDeposits,
            'booking_revenue_total' => $totalBookingRevenue,
            'payments_paid_total' => $totalPaid,
            'payments_pending_total' => $totalPending,
            'payments_overdue_count' => $overduePayments->count(),
            'unpaid_invoices_total' => $totalUnpaidInvoices,
            'unpaid_invoices_count' => $unpaidInvoices->count(),
            'total_properties' => $properties->count(),
            'active_properties' => $activeProps,
            'short_term_properties' => $properties->where('rental_mode', 'korttid')->count(),
            'long_term_properties' => $properties->where('rental_mode', 'langtid')->count(),
            'total_tenants' => $tenants->count(),
            'active_tenants' => $activeTenants,
            'active_contracts' => $activeContracts->count(),
            'active_bookings' => $activeBookings->count(),
        ];

        $locationsPayload = [
            'source_app' => 'Appendix Properties',
            'company_id' => $companyId,
            'locations' => $locations,
            'consolidated' => $consolidated,
        ];

        $externalDataPayload = [
            'app_name' => 'Appendix Properties',
            'app_id' => $companyId,
            'data_type' => 'eiendom_økonomi',
            'period' => $period,
            'metrics' => [
                'revenue_year' => $totalIncomeYear,
                'expenses_year' => $totalExpensesYear,
                'profit_year' => $totalIncomeYear - $totalExpensesYear,
                'revenue_month' => $totalIncomeMonth,
                'expenses_month' => $totalExpensesMonth,
                'profit_month' => $totalIncomeMonth - $totalExpensesMonth,
                'monthly_rent_total' => $totalMonthlyRent,
                'deposits_held' => $totalDeposits,
                'booking_revenue_total' => $totalBookingRevenue,
                'payments_paid_total' => $totalPaid,
                'payments_pending_total' => $totalPending,
                'payments_overdue_count' => $overduePayments->count(),
                'unpaid_invoices_total' => $totalUnpaidInvoices,
                'unpaid_invoices_count' => $unpaidInvoices->count(),
                'total_properties' => $properties->count(),
                'active_properties' => $activeProps,
                'active_contracts' => $activeContracts->count(),
                'active_bookings' => $activeBookings->count(),
                'active_tenants' => $activeTenants,
                'properties_rented' => $propertyDetails->where('rental_status', 'utleid')->count(),
                'properties_available' => $propertyDetails->where('rental_status', 'ledig')->count(),
            ],
            'raw_data' => [
                'consolidated' => $consolidated,
                'properties' => $propertyDetails->all(),
            ],
            'notes' => 'Automatisk synkronisering ' . $now->toIso8601ZuluString('millisecond'),
        ];

        // Send begge kall parallelt
        $responses = Http::pool(fn (Pool $pool) => [
            $pool->as('locations')->timeout(60)->asJson()->post($locationsUrl, $locationsPayload),
            $pool->as('external')->timeout(60)->asJson()->post($externalDataUrl, $externalDataPayload),
        ]);

        return [
            'success' => true,
            'message' => 'Data sendt til Appendix Holding',
            'locations_sent' => count($locations),
            'timestamp' => $now->toIso8601ZuluString('millisecond'),
            'locations_result' => $this->decode($responses['locations']),
            'external_data_result' => $this->decode($responses['external']),
        ];
    }

    /** res.json().catch(() => ({status})) – ved nettverksfeil: {status: 0, error}. */
    private function decode(mixed $res): mixed
    {
        if (!$res instanceof Response) {
            return ['status' => 0, 'error' => $res instanceof \Throwable ? $res->getMessage() : 'Ukjent feil'];
        }
        $json = $res->json();
        return $json ?? ['status' => $res->status()];
    }
}
