<?php

namespace Tests\Concerns;

use App\Models\Application;
use App\Models\CircularOffering;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\OfferingService;

trait BuildsApplications
{
    use BuildsCirculars;

    protected function openOffering(array $setup = [], array $circular = [], string $status = 'published'): CircularOffering
    {
        $circular = $this->makeCircular($circular, $status);
        app(OfferingService::class)->setup($circular, $this->setupPayload($setup));

        return $circular->offerings()->firstOrFail();
    }

    protected function docId(string $key): int
    {
        return (int) DocumentType::where('key', $key)->value('id');
    }

    protected function startPayload(CircularOffering $offering, array $override = []): array
    {
        return array_merge([
            'circular_id' => $offering->circular_id,
            'circular_offering_id' => $offering->id,
            'version_id' => $this->versionId('Bengali'),
            'shift_id' => $this->shiftId('Morning'),
            'admission_category_id' => $this->categoryId('Civil'),
        ], $override);
    }

    protected function startApplication(User $student, CircularOffering $offering, array $override = []): Application
    {
        $this->actingAs($student)->post(route('student.applications.start'), $this->startPayload($offering, $override));

        return Application::where('user_id', $student->id)->latest('id')->firstOrFail();
    }

    protected function step1(array $o = []): array
    {
        return array_merge([
            'name' => 'AYUSHI MAZUMDER', 'name_bn' => 'আয়ুশি মজুমদার', 'dob' => '2018-11-20', 'brn' => '20181591612150432',
            'phone1' => '01817728017', 'phone2' => '', 'blood' => 'A+', 'nation' => 'Bangladeshi', 'religion' => 'Hindu', 'gender' => 'Female',
        ], $o);
    }

    protected function step2(array $o = []): array
    {
        $data = ['guardian_type' => 'Father', 'guardian_rel' => 'Father'];
        foreach (['father' => 'RONY MAZUMDER', 'mother' => 'SANGITA DUTTA', 'guardian' => 'RONY MAZUMDER'] as $role => $name) {
            $data += [
                "{$role}_name" => $name, "{$role}_phone" => '01817728017', "{$role}_edu" => 'Degree', "{$role}_nid" => '1592812508314',
                "{$role}_occ" => 'Service', "{$role}_desig" => 'Manager', "{$role}_office" => 'Chattogram', "{$role}_income" => '70000.00',
            ];
        }

        return array_merge($data, $o);
    }

    protected function step3(array $o = []): array
    {
        return array_merge(['addr_present' => 'Pahartali, Chattogram', 'addr_permanent' => 'Fatikchari, Chattogram', 'addr_guardian' => ''], $o);
    }

    protected function step4(array $o = []): array
    {
        return array_merge(['prev_inst' => "St. Xavier's School", 'prev_class' => 'KG', 'prev_result' => '', 'prev_roll' => '13', 'prev_version' => 'Bangla Version'], $o);
    }

    protected function saveStep(User $student, Application $application, int $step, array $data)
    {
        return $this->actingAs($student)->put(route('student.applications.step.save', [$application, $step]), $data);
    }

    protected function completeSteps(User $student, Application $application, array $overrides = []): void
    {
        foreach ([1, 2, 3, 4] as $step) {
            $this->saveStep($student, $application, $step, $this->{"step{$step}"}($overrides[$step] ?? []));
        }
    }

    protected function submit(User $student, Application $application, array $data = ['declaration' => 1])
    {
        return $this->actingAs($student)->post(route('student.applications.submit', $application), $data);
    }

    /** A submitted application ready for review. */
    protected function submittedApplication(?User $student = null, ?CircularOffering $offering = null, array $overrides = []): Application
    {
        $student ??= User::factory()->create();
        $offering ??= $this->openOffering();
        $application = $this->startApplication($student, $offering);
        $this->completeSteps($student, $application, $overrides);
        $this->submit($student, $application);

        return $application->fresh();
    }
}
