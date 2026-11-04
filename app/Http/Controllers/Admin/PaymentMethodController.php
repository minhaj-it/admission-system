<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Services\PaymentMethodService;
use App\Support\PaymentMethods;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentMethodController extends Controller
{
    public function index(): View
    {
        $methods = PaymentMethod::orderBy('sort_order')->get();

        return view('admin.payment-methods.index', [
            'methods' => $methods,
            'status' => $methods->mapWithKeys(fn ($m) => [$m->key => [
                'available' => PaymentMethods::isAvailable($m->key),
                'credentials' => PaymentMethods::credentialStatus($m->key),
                'problem' => PaymentMethods::notReadyReason($m),
            ]]),
            'mode' => config('payments.shurjopay.mode') === 'live' ? 'Live' : 'Sandbox',
        ]);
    }

    public function activate(PaymentMethod $method, PaymentMethodService $service): RedirectResponse
    {
        $service->activate($method);

        return redirect()->route('admin.payment-methods.index')->with('status', $method->name . ' is now the payment method students see.');
    }

    public function deactivate(PaymentMethod $method, PaymentMethodService $service): RedirectResponse
    {
        $service->deactivate($method);

        return redirect()->route('admin.payment-methods.index')->with('status', $method->name . ' switched off.');
    }

    public function instructions(Request $request, PaymentMethod $method, PaymentMethodService $service): RedirectResponse
    {
        abort_unless($method->key === 'manual', 404);

        $data = $request->validate(['instructions' => ['nullable', 'string', 'max:2000']]);

        $service->updateInstructions($method, $data['instructions'] ?? null);

        return redirect()->route('admin.payment-methods.index')->with('status', 'Instructions saved.');
    }
}
