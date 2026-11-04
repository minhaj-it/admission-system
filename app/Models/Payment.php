<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    public const INITIATED = 'initiated';
    public const PENDING = 'pending';
    public const MANUAL_PENDING = 'manual_pending';
    public const SUCCESS = 'success';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';
    public const MANUAL_REJECTED = 'manual_rejected';
    public const REFUNDED = 'refunded';

    /** status => [label, tag class] */
    public const STATUSES = [
        self::INITIATED => ['Started', 'tag-amber'],
        self::PENDING => ['Pending', 'tag-amber'],
        self::MANUAL_PENDING => ['Waiting for verification', 'tag-amber'],
        self::SUCCESS => ['Successful', 'tag-green'],
        self::FAILED => ['Failed', 'tag-rose'],
        self::CANCELLED => ['Cancelled', 'tag-rose'],
        self::MANUAL_REJECTED => ['Rejected', 'tag-rose'],
        self::REFUNDED => ['Refunded', 'tag-blue'],
    ];

    // Nothing is mass-assignable: PaymentService sets every field, so amount, status and ownership can never come from a request.
    protected $fillable = [];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'verified_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
    public function method(): BelongsTo { return $this->belongsTo(PaymentMethod::class, 'payment_method_id'); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
    public function events(): HasMany { return $this->hasMany(PaymentEvent::class)->orderBy('id'); }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusTag(): string
    {
        return self::STATUSES[$this->status][1] ?? 'tag-blue';
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::INITIATED, self::PENDING, self::MANUAL_PENDING], true);
    }
}
