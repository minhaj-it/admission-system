<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PaymentMethod;
use App\Support\PaymentMethods;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentMethodService
{
    /** Exactly one method is offered to students: switching one on switches the others off. */
    public function activate(PaymentMethod $method): void
    {
        if ($reason = PaymentMethods::notReadyReason($method)) {
            throw ValidationException::withMessages(['method' => $reason]);
        }

        DB::transaction(function () use ($method) {
            $previous = PaymentMethod::where('is_active', true)->pluck('key')->all();
            PaymentMethod::query()->update(['is_active' => false]);
            PaymentMethod::whereKey($method->id)->update(['is_active' => true]);

            AuditLog::record('payment_method.activated', $method, ['active' => $previous], ['active' => [$method->key]]);
        });
    }

    public function deactivate(PaymentMethod $method): void
    {
        if (! $method->is_active) {
            return;
        }

        PaymentMethod::whereKey($method->id)->update(['is_active' => false]);
        AuditLog::record('payment_method.deactivated', $method, ['active' => [$method->key]], ['active' => []]);
    }

    public function updateInstructions(PaymentMethod $method, ?string $instructions): void
    {
        $old = $method->instructions;
        $method->forceFill(['instructions' => $instructions])->save();

        // an active manual method must keep readable instructions
        if ($method->key === 'manual' && $method->is_active && blank($instructions)) {
            $method->forceFill(['is_active' => false])->save();
        }

        AuditLog::record('payment_method.instructions_updated', $method, ['instructions' => $old ? '(text)' : null], ['instructions' => $instructions ? '(text)' : null]);
    }
}
