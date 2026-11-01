<?php

namespace App\Models;

use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Application extends Model
{
    public const DRAFT = 'draft';
    public const SUBMITTED = 'submitted';
    public const UNDER_REVIEW = 'under_review';
    public const CORRECTION = 'correction_requested';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const WAITLISTED = 'waitlisted';

    /** status => [label, tag class] */
    public const STATUSES = [
        self::DRAFT => ['Draft', 'tag-amber'],
        self::SUBMITTED => ['Submitted', 'tag-blue'],
        self::UNDER_REVIEW => ['Under review', 'tag-blue'],
        self::CORRECTION => ['Correction requested', 'tag-amber'],
        self::APPROVED => ['Approved', 'tag-green'],
        self::REJECTED => ['Rejected', 'tag-rose'],
        self::WAITLISTED => ['Waitlisted', 'tag-amber'],
    ];

    /** Allowed staff transitions. A student can only move draft/correction_requested -> submitted by submitting. */
    public const TRANSITIONS = [
        self::SUBMITTED => [self::UNDER_REVIEW, self::APPROVED, self::REJECTED, self::WAITLISTED, self::CORRECTION],
        self::UNDER_REVIEW => [self::APPROVED, self::REJECTED, self::WAITLISTED, self::CORRECTION],
        self::WAITLISTED => [self::APPROVED, self::REJECTED, self::UNDER_REVIEW],
        self::APPROVED => [self::UNDER_REVIEW],
        self::REJECTED => [self::UNDER_REVIEW],
        self::CORRECTION => [self::REJECTED],
    ];

    // Nothing is mass-assignable: every field is set explicitly by ApplicationService / ReviewService.
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'steps_done' => 'array',
            'fee_snapshot' => 'array',
            'submitted_at' => 'datetime',
            'consent_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function circular(): BelongsTo { return $this->belongsTo(AdmissionCircular::class, 'circular_id'); }
    public function offering(): BelongsTo { return $this->belongsTo(CircularOffering::class, 'circular_offering_id'); }
    public function version(): BelongsTo { return $this->belongsTo(Version::class); }
    public function shift(): BelongsTo { return $this->belongsTo(Shift::class); }
    public function group(): BelongsTo { return $this->belongsTo(StudyGroup::class, 'study_group_id'); }
    public function category(): BelongsTo { return $this->belongsTo(AdmissionCategory::class, 'admission_category_id'); }
    public function profile(): HasOne { return $this->hasOne(StudentProfile::class); }
    public function guardians(): HasMany { return $this->hasMany(Guardian::class); }
    public function addresses(): HasMany { return $this->hasMany(StudentAddress::class); }
    public function previous(): HasOne { return $this->hasOne(PreviousAcademicRecord::class); }
    public function documents(): HasMany { return $this->hasMany(ApplicationDocument::class); }
    public function histories(): HasMany { return $this->hasMany(ApplicationStatusHistory::class)->orderBy('id'); }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::DRAFT, self::CORRECTION], true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusTag(): string
    {
        return self::STATUSES[$this->status][1] ?? 'tag-blue';
    }

    public function stepDone(int $step): bool
    {
        return in_array($step, $this->steps_done ?? [], true);
    }

    public function nextApplicationNumber(): string
    {
        $n = NumberSequence::next('application_no:' . $this->circular_id);

        return 'AP' . str_pad((string) $this->circular_id, 2, '0', STR_PAD_LEFT) . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }

    /** HMAC of the digits of a birth registration number (never the number itself) for duplicate detection. */
    public static function dedupeKeyFor(string $birthRegNo): string
    {
        return hash_hmac('sha256', preg_replace('/\D/', '', $birthRegNo), (string) config('app.key'));
    }
}
