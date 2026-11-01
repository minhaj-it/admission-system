<?php

namespace App\Support;

final class ApplicantMask
{
    /** Keep only the last four characters of an identifier. */
    public static function tail(?string $value): string
    {
        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        return str_repeat('*', max(0, strlen($value) - 4)) . substr($value, -4);
    }
}
