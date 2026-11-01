<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionCircular extends Model
{
    public const TYPES = ['school' => 'School', 'college' => 'College'];

    /** key => [label, description, icon, enabled by default] */
    public const DOWNLOADS = [
        'admit_card' => ['Admit Card', 'Students can download their admit card', 'bi-person-badge', true],
        'application_form' => ['Application Form', 'Students can download their submitted application', 'bi-file-earmark-text', true],
        'id_card' => ['ID Card', 'Student identity card', 'bi-credit-card-2-front', false],
        'bus_card' => ['Bus Card', 'Transport card for school bus service', 'bi-bus-front', false],
        'library_card' => ['Library Card', 'Library membership card', 'bi-book', false],
    ];

    // status is never mass-assignable from a request; services set it explicitly.
    protected $fillable = [
        'type', 'title', 'description', 'opens_at', 'closes_at', 'circular_url', 'video_url',
        'portal_host', 'unique_birth_id',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'unique_birth_id' => 'boolean',
            'downloads' => 'array',
        ];
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(CircularOffering::class, 'circular_id');
    }

    /** draft | scheduled | open | closed | archived - derived from the stored status and the dates. */
    public function effectiveStatus(): string
    {
        if ($this->status === 'archived' || $this->status === 'draft') {
            return $this->status;
        }

        $now = now();

        return match (true) {
            $now->lt($this->opens_at) => 'scheduled',
            $now->gt($this->closes_at) => 'closed',
            default => 'open',
        };
    }

    public function isOpen(): bool
    {
        return $this->effectiveStatus() === 'open';
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'published')->where('opens_at', '<=', now())->where('closes_at', '>=', now());
    }

    public function downloadEnabled(string $key): bool
    {
        return (bool) ($this->downloads[$key] ?? self::DOWNLOADS[$key][3] ?? false);
    }
}
