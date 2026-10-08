<?php

use Illuminate\Support\Facades\Schedule;

/**
 * Planlagte jobber (erstatter base44/workflows/*.jsonc). Kjøres av scheduler-containeren (schedule:work).
 * Hver linje tilsvarer én workflow. name() må stå FØR withoutOverlapping().
 * Base44-workflowene var definert i UTC – derfor ->timezone('UTC') på cron-jobbene.
 *
 * Entity-workflowen «Contract status guard (update)» er IKKE en planlagt jobb: den er
 * App\Observers\ContractObserver (registrert i App\Providers\FunctionsServiceProvider).
 */
// Av som standard (SCHEDULER_ENABLED) – Base44 kjører fortsatt de samme workflowene til cutover.
if (!config('services.functions.scheduler_enabled')) {
    return;
}

$run = fn (string $class) => fn () => app($class)(null, []);

// «Velkomst-e-post for inviterte (sjekk hvert 10. min)» – interval 10 min
Schedule::call(fn () => app(\App\Functions\WelcomeOnUserRegistered::class)(null, []))
    ->name('welcome-on-user-registered')->everyTenMinutes()->withoutOverlapping();

// «Overvåk nettsidene (hvert 5. min)» – interval 5 min
Schedule::call($run(\App\Functions\OvervakNettsider::class))
    ->name('overvak-nettsider')->everyFiveMinutes()->withoutOverlapping();

// «Send data til Appendix Holding» – interval 30 min
Schedule::call($run(\App\Functions\SendToHolding::class))
    ->name('send-to-holding')->everyThirtyMinutes()->withoutOverlapping();

// «Daglig RLS-sikkerhetsaudit» – cron 0 0 * * * UTC
Schedule::call($run(\App\Functions\RunSecurityAudit::class))
    ->name('run-security-audit')->cron('0 0 * * *')->timezone('UTC')->withoutOverlapping();

// «Nattlig Azure OneDrive Backup» – cron 0 2 * * * UTC (etter at pgbackup har skrevet nattens dump)
Schedule::call($run(\App\Functions\AzureOneDriveBackup::class))
    ->name('azure-onedrive-backup')->cron('0 2 * * *')->timezone('UTC')->withoutOverlapping();

// «Generer serviceplan-oppgaver» – cron 0 4 * * * UTC
Schedule::call($run(\App\Functions\GenerateScheduledMaintenance::class))
    ->name('generate-scheduled-maintenance')->cron('0 4 * * *')->timezone('UTC')->withoutOverlapping();

// «Daglig kontraktsfrister-påminnelse» – cron 30 5 * * * UTC
Schedule::call($run(\App\Functions\ContractDeadlineReminders::class))
    ->name('contract-deadline-reminders')->cron('30 5 * * *')->timezone('UTC')->withoutOverlapping();

// «Contract deadline reminders (daily)» – cron 0 6 * * * UTC: DUPLIKAT av linjen over (samme funksjon,
// ville sendt alle påminnelsene to ganger daglig). Bevisst ikke aktivert – fjern kommentaren om det er ønsket.
// Schedule::call($run(\App\Functions\ContractDeadlineReminders::class))
//     ->name('contract-deadline-reminders-2')->cron('0 6 * * *')->timezone('UTC')->withoutOverlapping();

// «Geilo Lodge – Servicepåminnelse» – cron 0 6 * * 1 UTC (mandager; kaller checkPaymentReminders)
Schedule::call($run(\App\Functions\CheckPaymentReminders::class))
    ->name('check-payment-reminders')->cron('0 6 * * 1')->timezone('UTC')->withoutOverlapping();

// «Årlig KPI-leiejustering påminnelse» – cron 0 6 1 * * UTC (1. hver måned, slik workflowen var satt opp)
Schedule::call($run(\App\Functions\KpiRentAdjustmentReminder::class))
    ->name('kpi-rent-adjustment-reminder')->cron('0 6 1 * *')->timezone('UTC')->withoutOverlapping();

// «Daglig synk til master hub» – cron 30 23 * * * UTC
Schedule::call($run(\App\Functions\SyncAllToMaster::class))
    ->name('sync-all-to-master')->cron('30 23 * * *')->timezone('UTC')->withoutOverlapping();
