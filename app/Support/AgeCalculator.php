<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class AgeCalculator
{
    /** Completed months between the date of birth and the reference date; null if born after the reference date. */
    public static function months(CarbonInterface $dob, CarbonInterface $on): ?int
    {
        if ($dob->gt($on)) {
            return null;
        }

        $months = ($on->year - $dob->year) * 12 + ($on->month - $dob->month);

        if ($on->day < $dob->day) {
            $months--;
        }

        return $months;
    }
}
