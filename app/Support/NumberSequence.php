<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class NumberSequence
{
    /** Next value of a named counter. The row is locked, so concurrent callers never receive the same number. */
    public static function next(string $key): int
    {
        return DB::transaction(function () use ($key) {
            DB::table('number_sequences')->insertOrIgnore(['key' => $key, 'last_value' => 0]);

            $row = DB::table('number_sequences')->where('key', $key)->lockForUpdate()->first();
            $next = (int) $row->last_value + 1;

            DB::table('number_sequences')->where('key', $key)->update(['last_value' => $next]);

            return $next;
        });
    }
}
