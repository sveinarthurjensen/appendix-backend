<?php

use Illuminate\Support\Facades\Schedule;

/**
 * Planlagte jobber (erstatter base44/workflows/*.jsonc). Kjøres av scheduler-containeren (schedule:work).
 * Flere kommer i gruppe B; hver linje tilsvarer én workflow. name() må stå FØR withoutOverlapping().
 */
Schedule::call(fn () => app(\App\Functions\WelcomeOnUserRegistered::class)(null, []))
    ->name('welcome-on-user-registered')->everyTenMinutes()->withoutOverlapping();
