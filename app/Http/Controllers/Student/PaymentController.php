<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Institution;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function create(Application $application, PaymentService $payments): View|RedirectResponse
    {
        Gate::authorize('view', $application);

        $application->load(['circular', 'offering.schoolClass', 'offering.session', 'version', 'shift', 'group', 'category', 'profile']);

        if ($application->isPaid()) {
            return redirect()->route('student.applications.show', $application)->with('status', 'This application is already paid.');
        }

        return view('student.payments.create', [
            'application' => $application,
            'institute' => Institution::current(),
            'method' => $payments->activeMethod(),
            'canPay' => $payments->canPay($application),
            'lines' => collect($application->fee_snapshot['lines'] ?? [])->map(fn ($minor) => Money::format((int) $minor)),
            'total' => Money::format((int) $application->payable_minor),
            'pending' => $application->payments()->where('status', Payment::MANUAL_PENDING)->first(),
            'last' => $application->payments()->whereIn('status', [Payment::MANUAL_REJECTED, Payment::FAILED, Payment::CANCELLED])->first(),
        ]);
    }

    public function manual(Request $request, Application $application, PaymentService $payments): RedirectResponse
    {
        Gate::authorize('view', $application);

        $request->merge(['sender_no' => \App\Models\User::normalizeMobile((string) $request->input('sender_no'))]);

        $data = $request->validate([
            'sender_no' => ['required', 'regex:' . \App\Support\FormOptions::PHONE_REGEX],
            'manual_trx_id' => ['required', 'regex:/^[A-Za-z0-9]{6,40}$/'],
        ], [
            'sender_no.regex' => 'Enter the 11-digit mobile number you paid from.',
            'manual_trx_id.regex' => 'Enter the transaction id exactly as shown in your payment message (letters and numbers only).',
        ]);

        $payments->submitManual($application, $data);

        return redirect()->route('student.applications.show', $application)
            ->with('status', 'Payment details submitted. The office will verify them shortly.');
    }

    public function gateway(Request $request, Application $application, PaymentService $payments): RedirectResponse
    {
        Gate::authorize('view', $application);

        // the student leaves for the gateway's own page
        return redirect()->away($payments->startShurjoPay($application, (string) $request->ip()));
    }

    public function shurjopayReturn(Request $request, PaymentService $payments): RedirectResponse
    {
        return $this->afterGateway($request, $payments, 'return');
    }

    public function shurjopayCancel(Request $request, PaymentService $payments): RedirectResponse
    {
        return $this->afterGateway($request, $payments, 'cancel');
    }

    /** The browser coming back proves nothing: the payment is looked up and verified with the gateway first. */
    private function afterGateway(Request $request, PaymentService $payments, string $source): RedirectResponse
    {
        $orderId = (string) $request->validate(['order_id' => ['required', 'string', 'max:60']])['order_id'];

        $payment = $payments->handleReturn($request->user(), $orderId, $source);
        $application = $payment->application;

        return match ($payment->status) {
            Payment::SUCCESS => redirect()->route('student.applications.show', $application)->with('status', 'Payment received. Thank you!'),
            Payment::CANCELLED => redirect()->route('student.payments.create', $application)->with('error', 'The payment was cancelled.'),
            Payment::FAILED => redirect()->route('student.payments.create', $application)->with('error', 'The payment could not be completed. You have not been charged for this application.'),
            default => redirect()->route('student.applications.show', $application)->with('status', 'We are still waiting for the payment confirmation. This page will show it as soon as it arrives.'),
        };
    }
}
