<?php

namespace Tests\Concerns;

use App\Models\AcademicSession;
use App\Models\AdmissionCategory;
use App\Models\AdmissionCircular;
use App\Models\SchoolClass;
use App\Models\Shift;
use App\Models\StudyGroup;
use App\Models\Version;

trait BuildsCirculars
{
    protected function classId(string $name): int { return (int) SchoolClass::where('name', $name)->value('id'); }
    protected function sessionId(string $name): int { return (int) AcademicSession::where('name', $name)->value('id'); }
    protected function versionId(string $name): int { return (int) Version::where('name', $name)->value('id'); }
    protected function shiftId(string $name): int { return (int) Shift::where('name', $name)->value('id'); }
    protected function groupId(string $name): int { return (int) StudyGroup::where('name', $name)->value('id'); }
    protected function categoryId(string $name): int { return (int) AdmissionCategory::where('name', $name)->value('id'); }

    protected function makeCircular(array $attributes = [], string $status = 'published'): AdmissionCircular
    {
        $circular = new AdmissionCircular($attributes + [
            'type' => 'school',
            'title' => 'Admission Circular - 2027',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addMonth(),
            'circular_url' => 'https://example.test/circular.pdf',
            'portal_host' => 'example.test',
            'unique_birth_id' => false,
        ]);
        $circular->status = $status;
        $circular->save();

        return $circular;
    }

    protected function setupPayload(array $override = []): array
    {
        return array_merge([
            'programs' => [$this->classId('One')],
            'academic_session_id' => $this->sessionId('2027'),
            'shifts' => [$this->shiftId('Morning')],
            'versions' => [$this->versionId('Bengali'), $this->versionId('English')],
            'categories' => [$this->categoryId('Civil')],
            'roll_scope' => 'class_version',
            'form_fee' => '500',
            'bank_charge' => '30',
            'gender_rule' => 'both',
            'is_active' => 1,
        ], $override);
    }

    protected function circularPayload(array $override = []): array
    {
        return array_merge([
            'type' => 'school',
            'title' => 'Admission Circular - 2028',
            'opens_at' => '2028-01-05T00:00',
            'closes_at' => '2028-01-30T23:59',
            'circular_url' => 'https://example.test/circular.pdf',
            'portal_host' => 'example.test',
            'is_active' => 1,
        ], $override);
    }
}
