<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentController extends Controller
{
    /** tab => statuses */
    private const TABS = [
        'success' => ['Successful', [Payment::SUCCESS]],
        'pending' => ['Pending', [Payment::INITIATED, Payment::PENDING, Payment::MANUAL_PENDING]],
        'failed' => ['Failed / cancelled', [Payment::FAILED, Payment::CANCELLED, Payment::MANUAL_REJECTED]],
        'all' => ['All', null],
    ];

    public function index(Request $request): View
    {
        $f = $request->validate([
            'tab' => ['nullable', Rule::in(array_keys(self::TABS))],
            'q' => ['nullable', 'string', 'max:100'],
            'gateway' => ['nullable', Rule::in(['shurjopay', 'manual', 'free'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $tab = $f['tab'] ?? 'success';
        $q = trim((string) ($f['q'] ?? ''));

        $base = Payment::query()
            ->when($f['gateway'] ?? null, fn ($query, $v) => $query->where('gateway', $v))
            ->when($f['from'] ?? null, fn ($query, $v) => $query->whereDate('created_at', '>=', $v))
            ->when($f['to'] ?? null, fn ($query, $v) => $query->whereDate('created_at', '<=', $v))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . addcslashes($q, '\\%_') . '%';
                $digits = preg_replace('/\D/', '', $q);
                $query->where(function ($w) use ($like, $digits) {
                    $w->where('merchant_ref', 'like', $like)->orWhere('manual_trx_id', 'like', $like)->orWhere('gateway_txn_id', 'like', $like)
                        ->orWhereHas('application', fn ($a) => $a->where('application_no', 'like', $like)
                            ->orWhereHas('profile', fn ($p) => $p->where('name', 'like', $like)));
                    if ($digits !== '' && strlen($digits) >= 6) {
                        $w->orWhereHas('application', fn ($a) => $a->where('tracking_no', (int) $digits));
                    }
                });
            });

        $counts = [];
        foreach (self::TABS as $key => [, $statuses]) {
            $counts[$key] = (clone $base)->when($statuses, fn ($query) => $query->whereIn('status', $statuses))->count();
        }

        $payments = (clone $base)
            ->when(self::TABS[$tab][1], fn ($query, $statuses) => $query->whereIn('status', $statuses))
            ->with(['application.profile', 'application.offering.schoolClass', 'application.roll', 'method'])
            ->latest('id')->paginate(20)->withQueryString();

        return view('admin.payments.index', [
            'payments' => $payments, 'tab' => $tab, 'tabs' => self::TABS, 'counts' => $counts, 'filters' => $f,
            'successTotal' => Money::format((int) (clone $base)->where('status', Payment::SUCCESS)->sum('amount_minor')),
        ]);
    }

    public function show(Payment $payment): View
    {
        $payment->load(['application.profile', 'application.offering.schoolClass', 'application.roll', 'method', 'verifier', 'events']);

        return view('admin.payments.show', ['payment' => $payment, 'amount' => Money::format((int) $payment->amount_minor)]);
    }

    public function approve(Request $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        $payments->approveManual($request->user(), $payment);

        return redirect()->back()->with('status', 'Payment approved. The application now has its roll number.');
    }

    public function reject(Request $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate(['remark' => ['nullable', 'string', 'max:255']]);

        $payments->rejectManual($request->user(), $payment, $data['remark'] ?? null);

        return redirect()->back()->with('status', 'Payment rejected.');
    }

    public function recheck(Payment $payment, PaymentService $payments): RedirectResponse
    {
        $fresh = $payments->verify($payment, 'verify');

        return redirect()->back()->with('status', 'Checked with the gateway: ' . $fresh->statusLabel() . '.');
    }
}
