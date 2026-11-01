<?php

namespace Tests\Feature\Admission;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsApplications;
use Tests\TestCase;

class ApplicationSecurityTest extends TestCase
{
    use BuildsApplications, RefreshDatabase;

    public function test_another_student_cannot_view_edit_or_submit_an_application(): void
    {
        $owner = User::factory()->create();
        $application = $this->startApplication($owner, $this->openOffering());
        $this->completeSteps($owner, $application);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('student.applications.show', $application))->assertForbidden();
        $this->actingAs($intruder)->get(route('student.applications.step', [$application, 1]))->assertForbidden();
        $this->saveStep($intruder, $application, 1, $this->step1(['name' => 'HACKED']))->assertForbidden();
        $this->submit($intruder, $application)->assertForbidden();

        $this->assertSame('AYUSHI MAZUMDER', $application->fresh()->profile->name);
        $this->assertSame('draft', $application->fresh()->status);
    }

    public function test_guests_and_staff_cannot_use_the_student_routes(): void
    {
        $owner = User::factory()->create();
        $application = $this->startApplication($owner, $this->openOffering());

        auth()->logout();
        $this->get(route('student.applications.show', $application))->assertRedirect(route('login'));
        $this->post(route('student.applications.start'), $this->startPayload($application->offering))->assertRedirect(route('login'));

        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->get(route('student.applications.show', $application))->assertForbidden();
        $this->actingAs($staff)->post(route('student.applications.start'), $this->startPayload($application->offering))->assertForbidden();
    }

    public function test_applications_are_addressed_by_uuid_not_by_a_guessable_number(): void
    {
        $owner = User::factory()->create();
        $application = $this->startApplication($owner, $this->openOffering());

        $this->actingAs($owner)->get('/student/applications/' . $application->id)->assertNotFound();
        $this->actingAs($owner)->get('/student/applications/' . $application->tracking_no)->assertNotFound();
        $this->actingAs($owner)->get('/student/applications/' . $application->uuid)->assertOk();
    }

    public function test_ownership_status_and_numbers_cannot_be_set_from_the_browser(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $application = $this->startApplication($owner, $this->openOffering());

        $this->saveStep($owner, $application, 1, $this->step1([
            'user_id' => $other->id, 'status' => 'approved', 'tracking_no' => 1, 'application_no' => 'X', 'payable_minor' => 1, 'uuid' => 'x',
        ]))->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame($owner->id, $application->user_id);
        $this->assertSame('draft', $application->status);
        $this->assertNotSame(1, $application->tracking_no);
        $this->assertNull($application->application_no);
        $this->assertNull($application->payable_minor);

        $this->actingAs($owner)->post(route('student.applications.start'), $this->startPayload($application->offering, [
            'version_id' => $this->versionId('English'), 'user_id' => $other->id, 'status' => 'approved', 'tracking_no' => 5,
        ]));
        $second = Application::where('version_id', $this->versionId('English'))->firstOrFail();
        $this->assertSame($owner->id, $second->user_id);
        $this->assertSame('draft', $second->status);
    }

    public function test_the_fee_comes_from_the_class_setup_never_from_the_request(): void
    {
        $student = User::factory()->create();
        $application = $this->startApplication($student, $this->openOffering(['form_fee' => '800', 'bank_charge' => '20']));
        $this->completeSteps($student, $application);
        $this->submit($student, $application, ['declaration' => 1, 'payable_minor' => 1, 'fee' => 0, 'amount' => 0]);

        $this->assertSame(82000, $application->fresh()->payable_minor);
    }

    public function test_the_application_still_matches_the_setup_it_was_started_with_after_the_fee_changes(): void
    {
        $student = User::factory()->create();
        $offering = $this->openOffering();
        $application = $this->startApplication($student, $offering);
        $this->completeSteps($student, $application);
        $this->submit($student, $application);

        $offering->update(['form_fee' => 9999]);

        $this->assertSame(53000, $application->fresh()->payable_minor, 'the snapshot taken at submission must not change');
    }
}
