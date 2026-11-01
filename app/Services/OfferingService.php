<?php

namespace App\Services;

use App\Models\AdmissionCircular;
use App\Models\AuditLog;
use App\Models\CircularOffering;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;

class OfferingService
{
    /**
     * "Class Setup": creates or updates one offering per selected class for the chosen session.
     * Re-saving the same class + session updates it, so the form can be used for editing.
     *
     * @return array{created: list<string>, updated: list<string>}
     */
    public function setup(AdmissionCircular $circular, array $data): array
    {
        $classes = SchoolClass::whereIn('id', $data['programs'])->ordered()->get();
        $result = ['created' => [], 'updated' => []];

        DB::transaction(function () use ($circular, $data, $classes, &$result) {
            foreach ($classes as $class) {
                $keys = [
                    'circular_id' => $circular->id,
                    'school_class_id' => $class->id,
                    'academic_session_id' => (int) $data['academic_session_id'],
                ];

                $offering = CircularOffering::where($keys)->first() ?? new CircularOffering();
                $exists = $offering->exists;

                if (! $exists) {
                    // the identifying columns are not mass-assignable, so they are set explicitly
                    $offering->circular_id = $keys['circular_id'];
                    $offering->school_class_id = $keys['school_class_id'];
                    $offering->academic_session_id = $keys['academic_session_id'];
                }
                $limitAge = ! empty($data['limit_age']);

                $offering->fill([
                    'roll_scope' => $data['roll_scope'],
                    'roll_prefix' => ($data['roll_prefix'] ?? null) ?: null,
                    'form_fee' => ($data['form_fee'] ?? '') === '' ? 0 : $data['form_fee'],
                    'admission_fee' => ($data['admission_fee'] ?? '') === '' ? 0 : $data['admission_fee'],
                    'uniform_fee' => ($data['uniform_fee'] ?? '') === '' ? 0 : $data['uniform_fee'],
                    'bank_charge' => ($data['bank_charge'] ?? '') === '' ? 0 : $data['bank_charge'],
                    'gender_rule' => $data['gender_rule'],
                    'seats' => ($data['seats'] ?? '') === '' ? null : (int) $data['seats'],
                    'limit_age' => $limitAge,
                    'age_on' => $limitAge ? ($data['age_on'] ?? null) : null,
                    'min_age_months' => $limitAge ? (int) $data['min_age_months'] : null,
                    'max_age_months' => $limitAge ? (int) $data['max_age_months'] : null,
                    'lottery_at' => ($data['lottery_at'] ?? null) ?: null,
                    'viva_at' => ($data['viva_at'] ?? null) ?: null,
                    'written_at' => ($data['written_at'] ?? null) ?: null,
                    'result_at' => ($data['result_at'] ?? null) ?: null,
                    'is_active' => ! empty($data['is_active']),
                ]);
                $offering->save();

                $offering->versions()->sync($data['versions']);
                $offering->shifts()->sync($data['shifts']);
                // Groups only apply to classes that use them; for the others the selection is ignored.
                $offering->groups()->sync($class->requires_group ? ($data['groups'] ?? []) : []);

                $categories = [];
                foreach ($data['categories'] as $categoryId) {
                    $quota = $data['quotas'][$categoryId] ?? null;
                    $categories[$categoryId] = ['quota_seats' => ($quota === null || $quota === '') ? null : (int) $quota];
                }
                $offering->categories()->sync($categories);

                $documents = [];
                foreach ($data['documents'] ?? [] as $documentId) {
                    $documents[$documentId] = ['is_required' => true];
                }
                $offering->documents()->sync($documents);

                $result[$exists ? 'updated' : 'created'][] = $class->name;
            }

            AuditLog::record('circular.class_setup', $circular, null, [
                'session_id' => (int) $data['academic_session_id'],
                'created' => $result['created'],
                'updated' => $result['updated'],
                'form_fee' => $data['form_fee'] ?? 0,
            ]);
        });

        return $result;
    }
}
