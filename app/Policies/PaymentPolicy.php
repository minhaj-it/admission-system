<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /** A student sees only the payments of their own applications; staff need payments.view. */
    public function view(User $user, Payment $payment): bool
    {
        if ($user->isStudent()) {
            return $payment->application->user_id === $user->id;
        }

        return $user->can('payments.view');
    }
}
