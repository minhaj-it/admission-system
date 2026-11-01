<?php

namespace App\Support;

use App\Models\Application;

/** Maps the flat form fields of the 5-step application to / from the related tables. */
final class ApplicationData
{
    public const PROFILE = [
        'name' => 'name', 'name_bn' => 'name_bn', 'dob' => 'dob', 'brn' => 'birth_reg_no', 'phone1' => 'emergency_phone',
        'phone2' => 'alt_phone', 'blood' => 'blood_group', 'nation' => 'nationality', 'religion' => 'religion', 'gender' => 'gender',
    ];

    public const PERSON = [
        'name' => 'name', 'phone' => 'phone', 'edu' => 'education', 'nid' => 'nid', 'occ' => 'occupation',
        'desig' => 'designation', 'office' => 'office_address', 'income' => 'monthly_income',
    ];

    public const ROLES = ['father', 'mother', 'guardian'];

    public const ADDRESS = ['addr_present' => 'present', 'addr_permanent' => 'permanent', 'addr_guardian' => 'guardian'];

    public const PREVIOUS = [
        'prev_inst' => 'institute', 'prev_class' => 'class_name', 'prev_result' => 'result', 'prev_roll' => 'roll', 'prev_version' => 'version_text',
    ];

    /** @return list<string> form field names that belong to a step */
    public static function stepKeys(int $step): array
    {
        return match ($step) {
            1 => array_keys(self::PROFILE),
            2 => array_merge(
                ...array_map(fn ($role) => array_map(fn ($k) => "{$role}_{$k}", array_keys(self::PERSON)), self::ROLES),
                ...[['guardian_type', 'guardian_rel']]
            ),
            3 => array_keys(self::ADDRESS),
            4 => array_keys(self::PREVIOUS),
            default => [],
        };
    }

    /** Everything saved so far as one flat array (used to pre-fill the form, to review and to re-validate on submit). */
    public static function flatten(Application $application): array
    {
        $out = [];

        if ($profile = $application->profile) {
            foreach (self::PROFILE as $key => $column) {
                $out[$key] = $column === 'dob' ? $profile->dob?->format('Y-m-d') : $profile->{$column};
            }
        }

        foreach ($application->guardians as $guardian) {
            foreach (self::PERSON as $key => $column) {
                $out["{$guardian->role}_{$key}"] = $guardian->{$column};
            }
            if ($guardian->role === 'guardian') {
                $out['guardian_type'] = $guardian->guardian_type;
                $out['guardian_rel'] = $guardian->relation;
            }
        }

        foreach ($application->addresses as $address) {
            $key = array_search($address->kind, self::ADDRESS, true);
            if ($key) {
                $out[$key] = $address->address;
            }
        }

        if ($previous = $application->previous) {
            foreach (self::PREVIOUS as $key => $column) {
                $out[$key] = $previous->{$column};
            }
        }

        return $out;
    }
}
