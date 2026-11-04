<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationRoll;
use App\Support\NumberSequence;

class RollAllocator
{
    /**
     * Gives an application its roll number once (called when its payment succeeds). The counter row is locked,
     * so two payments confirmed at the same moment never receive the same number; running it twice returns the first roll.
     */
    public function allocate(Application $application): ApplicationRoll
    {
        $existing = ApplicationRoll::where('application_id', $application->id)->first();
        if ($existing) {
            return $existing;
        }

        $offering = $application->offering;
        $scope = 'roll:' . $application->circular_id . ':' . $offering->school_class_id . ':' . $offering->academic_session_id;

        // Roll Type of the class setup decides where the numbering restarts
        if (in_array($offering->roll_scope, ['class_version', 'class_version_group'], true)) {
            $scope .= ':v' . $application->version_id;
        }
        if ($offering->roll_scope === 'class_version_group') {
            $scope .= ':g' . ($application->study_group_id ?? 0);
        }

        $sequence = NumberSequence::next($scope);

        $roll = new ApplicationRoll();
        $roll->application_id = $application->id;
        $roll->scope_key = $scope;
        $roll->sequence = $sequence;
        $roll->roll_no = ($offering->roll_prefix ?? '') . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
        $roll->save();

        return $roll;
    }
}
