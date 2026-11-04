<?php

use Illuminate\Support\Facades\Schedule;

// Re-checks gateway payments whose customer never came back to the site (see App\Console\Commands\ReconcilePayments).
Schedule::command('payments:reconcile')->everyFiveMinutes()->withoutOverlapping();
