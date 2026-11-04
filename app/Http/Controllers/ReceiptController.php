<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    public function show(Payment $payment): View
    {
        Gate::authorize('view', $payment);
        abort_unless($payment->status === Payment::SUCCESS, 404);

        $payment->load(['application.circular', 'application.offering.schoolClass', 'application.offering.session', 'application.version', 'application.shift', 'application.category', 'application.profile', 'application.roll', 'method']);

        return view('student.payments.receipt', [
            'payment' => $payment,
            'application' => $payment->application,
            'institute' => Institution::current(),
            'lines' => collect($payment->application->fee_snapshot['lines'] ?? [])->map(fn ($minor) => Money::format((int) $minor)),
            'total' => Money::format((int) $payment->amount_minor),
        ]);
    }
}
