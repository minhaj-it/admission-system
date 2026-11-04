<?php

namespace App\Support;

use App\Models\PaymentMethod;

final class PaymentMethods
{
    /** Methods that can be switched on in this release; the others are listed so the page matches the design. */
    public const AVAILABLE = ['manual', 'shurjopay'];

    public const ICONS = ['bkash' => 'bi-wallet2', 'nagad' => 'bi-wallet2', 'sslcommerz' => 'bi-credit-card', 'shurjopay' => 'bi-credit-card-2-front', 'manual' => 'bi-bank'];

    public static function isAvailable(string $key): bool
    {
        return in_array($key, self::AVAILABLE, true);
    }

    /** @return array<string,bool> credential name => is it set in .env (the values are never shown) */
    public static function credentialStatus(string $key): array
    {
        if ($key !== 'shurjopay') {
            return [];
        }

        return [
            'SHURJOPAY_USERNAME' => filled(config('payments.shurjopay.username')),
            'SHURJOPAY_PASSWORD' => filled(config('payments.shurjopay.password')),
            'SHURJOPAY_PREFIX' => filled(config('payments.shurjopay.prefix')),
        ];
    }

    /** Can this method be switched on right now? Returns an explanation when it cannot. */
    public static function notReadyReason(PaymentMethod $method): ?string
    {
        if (! self::isAvailable($method->key)) {
            return 'This payment method is not available yet.';
        }

        if ($method->key === 'manual' && blank($method->instructions)) {
            return 'Write the payment instructions (account numbers and steps) first.';
        }

        if ($method->key === 'shurjopay' && in_array(false, self::credentialStatus('shurjopay'), true)) {
            return 'ShurjoPay credentials are missing in the .env file.';
        }

        return null;
    }
}
