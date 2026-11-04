<?php

namespace App\Services\Gateways;

/** What the gateway's server-to-server verification said about a payment. */
final class GatewayResult
{
    public const SUCCESS = 'success';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';
    public const PENDING = 'pending';

    public function __construct(
        public readonly string $status,
        public readonly string $spOrderId,
        public readonly string $merchantRef,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly ?string $bankTrxId,
        public readonly ?string $channel,
        public readonly array $raw,
    ) {
    }
}
