<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/** Shared behaviour of the configurable lists (classes, versions, shifts, groups, sessions, categories, documents). */
trait MasterRecord
{
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** How many circular setups use this record; a record in use can be deactivated but not deleted. */
    abstract public function usageCount(): int;
}
