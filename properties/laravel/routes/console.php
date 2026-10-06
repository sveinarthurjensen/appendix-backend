<?php

use Illuminate\Support\Facades\Schedule;

/**
 * Planlagte jobber (erstatter base44/workflows/*.jsonc). Kjøres av scheduler-containeren (schedule:work).
 * Flere kommer i gruppe B; hver linje tilsvarer én workflow.
 */
Schedule::call(fn () => app(\App\Functions\WelcomeOnUserRegistered::class)(null, []))
    ->everyTenMinutes()->withoutOverlapping()->name('welcome-on-user-registered');
