<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use LogicException;

/** Append-only log of what the gateway (or staff) told us about a payment. Only whitelisted, non-sensitive fields are kept. */
class PaymentEvent extends Model
{
    public const UPDATED_AT = null;

    /** The only gateway fields that are ever stored: no card data, no customer details. */
    public const KEEP = ['order_id', 'customer_order_id', 'currency', 'amount', 'payable_amount', 'sp_code', 'sp_message', 'bank_status', 'bank_trx_id', 'invoice_no', 'method', 'transaction_status', 'date_time', 'message', 'reason'];

    protected $fillable = ['payment_id', 'source', 'outcome', 'payload', 'event_hash'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payment events are append-only.'));
        static::deleting(fn () => throw new LogicException('Payment events cannot be deleted.'));
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** Stores an event once: an identical repeated notification produces the same hash and is ignored. */
    public static function log(Payment $payment, string $source, string $outcome, array $payload = []): void
    {
        $payload = Arr::only($payload, self::KEEP);
        ksort($payload);

        static::query()->insertOrIgnore([
            'payment_id' => $payment->id,
            'source' => $source,
            'outcome' => $outcome,
            'payload' => $payload ? json_encode($payload) : null,
            'event_hash' => hash('sha256', $payment->id . '|' . $source . '|' . $outcome . '|' . json_encode($payload)),
            'created_at' => now(),
        ]);
    }
}
