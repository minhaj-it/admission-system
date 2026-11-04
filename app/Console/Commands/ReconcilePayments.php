<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile {--minutes=5 : only payments older than this} {--days=3 : ignore payments older than this}';

    protected $description = 'Asks the payment gateway about pending online payments whose customer never came back to the site';

    public function handle(PaymentService $payments): int
    {
        $checked = 0;
        $settled = 0;

        Payment::query()
            ->where('gateway', 'shurjopay')
            ->where('status', Payment::PENDING)
            ->whereNotNull('gateway_ref')
            ->where('created_at', '<=', now()->subMinutes((int) $this->option('minutes')))
            ->where('created_at', '>=', now()->subDays((int) $this->option('days')))
            ->orderBy('id')
            ->chunkById(50, function ($chunk) use ($payments, &$checked, &$settled) {
                foreach ($chunk as $payment) {
                    $checked++;
                    if ($payments->verify($payment, 'reconcile')->status !== Payment::PENDING) {
                        $settled++;
                    }
                }
            });

        $this->info("Checked {$checked} pending payment(s); {$settled} changed.");

        return self::SUCCESS;
    }
}
