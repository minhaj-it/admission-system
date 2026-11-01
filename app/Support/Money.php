<?php

namespace App\Support;

/** Amounts are stored as DECIMAL(12,2); arithmetic is done on integer paisa, never on floats. */
final class Money
{
    public static function toMinor(string|int|float|null $amount): int
    {
        return (int) round(((float) ($amount ?? 0)) * 100);
    }

    public static function format(int $minor): string
    {
        return '৳' . number_format($minor / 100, 2, '.', ',');
    }
}
