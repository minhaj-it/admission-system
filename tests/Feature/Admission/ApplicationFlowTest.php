<?php

namespace Tests\Feature\Admission;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsApplications;
use Tests\TestCase;

class ApplicationFlowTest extends TestCase
{
    use BuildsApplications, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_student_panel_offers_open_classes_or_says_there_are_none(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->get(route('student.home'))->assertOk()->assertSee('No class is open for admission right now.');

        $this->openOffering();

        $this->actingAs($student)->get(route('student.home'))->assertOk()->assertSee('Continue')->assertSee('Admission Circular - 2027')->assertDontSee('No class is open');
    }

    public function test_starting_creates_a_draft_with_a_tracking_number_and_history(): void
    {
        $student = User::factory()->create();
        $offering = $this->openOffering();

        $this->actingAs($student)->post(route('student.applications.start'), $this->startPayload($offering))->assertRedirect();

        $application = Application::firstOrFail();
        $this->assertSame('draft', $application->status);
        $this->assertSame($student->id, $application->user_id);
        $this->assertMatchesRegularExpression('/^\d{8}$/', (string) $application->tracking_no);
        $this->assertNull($application->application_no);
        $this->assertNotEmpty($application->uuid);
        $this->assertDatabaseHas('application_status_histories', ['application_id' => $application->id, 'to_status' => 'draft']);
    }

    public function test_starting_the_same_selection_again_resumes_instead_of_duplicating(): void
    {
        $student = User::factory()->create();
        $offering = $this->openOffering();

        $this->actingAs($student)->post(route('student.applications.start'), $this->startPayload($offering));
        $this->actingAs($student)->post(route('student.applications.start'), $this->startPayload($offering))->assertRedirect();

        $this->assertSame(1, Application::count());

        // the other version is a different application
        $this->actingAs($student)->post(route('student.applications.start'), $this->startPayload($offering, ['version_id' => $this->versionId('English')]));
        $this->assertSame(2, Application::count());
    }

    public function test_applications_can_only_start_for_what_the_circular_really_offers(): void
    {
        $student = User::factory()->create();
        $offering = $this->openOffering();
        $other = $this->classId('Two');

        $bad = [
            ['version_id' => 99999],
            ['shift_id' => $this->shiftId('Evening')],                 // not offered
            ['admission_category_id' => $this->categoryId('Army')],    // not offered
            ['circular_offering_id' => 99999],
            ['circular_id' => 99999],
        ];
        foreach ($bad as $override) {
            $this->actingAs($student)->post(route('student.applications.start'), $this->startPayload($offering, $override))->assertSessionHasErrors();
        }

        $this->assertSame(0, Application::count());
        $this->assertNotSame(0, $other);
    }

    public function test_closed_scheduled_draft_archived_circulars_and_inactive_classes_cannot_be_applied_to(): void
    {
        $student = User::factory()->create();

        foreach ([
            [['opens_at' => now()->addDay(), 'closes_at' => now()->addMonth()], 'published'],   // scheduled
            [['opens_at' => now()->subMonth(), 'closes_at' => now()->subDay()], 'published'],   // closed
            [[], 'draft'],
            [[], 'archived'],
        ] as [$dates, $status]) {
            $offering = $this->openOffering([], $dates, $status);
            $this->actingAs($student)->post(route('student.applications.start'), $this->startPayload($offering))->assertSessionHasErrors('circular');
        }

        $offering = $this->openOffering();
        $offering->update(['is_active' => false]);
        $this->actingAs($student)->post(route('student.applications.start'), $this->startPayload($offering))->assertSessionHasErrors('offering');

        $this->assertSame(0, Application::count());
    }

    public function test_a_group_is_required_when_the_class_offers_groups(): void
    {
        $student = User::factory()->create();
        $offering = $this->openOffering(['programs' => [$this->classId('Eleven')], 'groups' => [$this->groupId('Science')]]);

        $this->actingAs($student)->post(route('student.applications.start'), $this->startPayload($offering))->assertSessionHasErrors('study_group_id');
        $this->actingAs($student)->post(route('student.applications.start'), $this->startPayload($offering, ['study_group_id' => $this->groupId('Humanities')]))->assertSessionHasErrors('study_group_id');
        $this->actingAs($student)->post(route('student.applications.start'), $this->startPayload($offering, ['study_group_id' => $this->groupId('Science')]))->assertSessionHasNoErrors();

        $this->assertSame($this->groupId('Science'), Application::firstOrFail()->study_group_id);
    }

    public function test_all_step_pages_render_for_the_owner(): void
    {
        $student = User::factory()->create();
        $application = $this->startApplication($student, $this->openOffering());

        foreach ([1, 2, 3, 4, 5] as $step) {
            $this->actingAs($student)->get(route('student.applications.step', [$application, $step]))->assertOk()->assertSee('Student Admission Form');
        }
        $this->actingAs($student)->get(route('student.applications.step', [$application, 6]))->assertNotFound();
    }

    public function test_each_step_is_saved_and_prefilled(): void
    {
        $student = User::factory()->create();
        $application = $this->startApplication($student, $this->openOffering());

        $this->saveStep($student, $application, 1, $this->step1())->assertRedirect(route('student.applications.step', [$application, 2]));
        $this->saveStep($student, $application, 2, $this->step2())->assertRedirect(route('student.applications.step', [$application, 3]));
        $this->saveStep($student, $application, 3, $this->step3())->assertRedirect(route('student.applications.step', [$application, 4]));
        $this->saveStep($student, $application, 4, $this->step4())->assertRedirect(route('student.applications.step', [$application, 5]));

        $application->refresh();
        $this->assertSame([1, 2, 3, 4], $application->steps_done);
        $this->assertSame('AYUSHI MAZUMDER', $application->profile->name);
        $this->assertSame('Pahartali, Chattogram', $application->addresses->firstWhere('kind', 'present')->address);
        $this->assertSame(3, $application->guardians()->count());
        $this->assertSame('Bangla Version', $application->previous->version_text);

        $this->actingAs($student)->get(route('student.applications.step', [$application, 1]))->assertSee('AYUSHI MAZUMDER')->assertSee('01817728017');
        $this->actingAs($student)->get(route('student.applications.step', [$application, 5]))->assertSee('Personal Information')->assertSee('RONY MAZUMDER');
    }

    public function test_sensitive_numbers_are_encrypted_at_rest(): void
    {
        $student = User::factory()->create();
        $application = $this->startApplication($student, $this->openOffering());
        $this->completeSteps($student, $application);

        $this->assertSame('20181591612150432', $application->fresh()->profile->birth_reg_no);
        $this->assertStringNotContainsString('20181591612150432', (string) DB::table('student_profiles')->value('birth_reg_no'));
        $this->assertStringNotContainsString('1592812508314', (string) DB::table('guardians')->value('nid'));
    }

    public function test_each_step_validates_on_the_server(): void
    {
        $student = User::factory()->create();
        $application = $this->startApplication($student, $this->openOffering());

        $this->saveStep($student, $application, 1, $this->step1(['name' => '']))->assertSessionHasErrors('name');
        $this->saveStep($student, $application, 1, $this->step1(['phone1' => '12345']))->assertSessionHasErrors('phone1');
        $this->saveStep($student, $application, 1, $this->step1(['dob' => '2999-01-01']))->assertSessionHasErrors('dob');
        $this->saveStep($student, $application, 1, $this->step1(['dob' => 'not-a-date']))->assertSessionHasErrors('dob');
        $this->saveStep($student, $application, 1, $this->step1(['religion' => 'Pastafarian']))->assertSessionHasErrors('religion');
        $this->saveStep($student, $application, 1, $this->step1(['gender' => 'x']))->assertSessionHasErrors('gender');
        $this->saveStep($student, $application, 1, $this->step1(['brn' => '12']))->assertSessionHasErrors('brn');
        $this->saveStep($student, $application, 2, $this->step2(['father_phone' => 'abc']))->assertSessionHasErrors('father_phone');
        $this->saveStep($student, $application, 2, $this->step2(['mother_name' => '']))->assertSessionHasErrors('mother_name');
        $this->saveStep($student, $application, 2, $this->step2(['guardian_type' => 'Stranger']))->assertSessionHasErrors('guardian_type');
        $this->saveStep($student, $application, 2, $this->step2(['father_income' => '-5']))->assertSessionHasErrors('father_income');
        $this->saveStep($student, $application, 3, $this->step3(['addr_present' => '']))->assertSessionHasErrors('addr_present');

        $this->assertNull($application->fresh()->profile);
        $this->assertSame([], $application->fresh()->steps_done ?? []);
    }

    public function test_phone_numbers_with_a_country_code_are_normalised(): void
    {
        $student = User::factory()->create();
        $application = $this->startApplication($student, $this->openOffering());

        $this->saveStep($student, $application, 1, $this->step1(['phone1' => '+88 01817728017']))->assertSessionHasNoErrors();

        $this->assertSame('01817728017', $application->fresh()->profile->emergency_phone);
    }

    public function test_the_age_limit_and_gender_rule_are_enforced_by_the_server(): void
    {
        $student = User::factory()->create();
        $offering = $this->openOffering([
            'gender_rule' => 'female', 'limit_age' => 1, 'age_on' => '2027-01-01', 'min_age_months' => 48, 'max_age_months' => 72,
        ]);
        $application = $this->startApplication($student, $offering);

        // born 2018-11-20: 97 completed months on 2027-01-01, above the 72 month limit
        $this->saveStep($student, $application, 1, $this->step1(['dob' => '2018-11-20']))->assertSessionHasErrors('dob');
        $this->saveStep($student, $application, 1, $this->step1(['dob' => '2022-03-10']))->assertSessionHasNoErrors();   // 57 months
        $this->saveStep($student, $application, 1, $this->step1(['dob' => '2022-03-10', 'gender' => 'Male']))->assertSessionHasErrors('gender');
        $this->saveStep($student, $application, 1, $this->step1(['dob' => '2027-06-01']))->assertSessionHasErrors('dob'); // born after the reference date

        $this->assertSame('2022-03-10', $application->fresh()->profile->dob->format('Y-m-d'));
    }

    public function test_a_birth_registration_number_is_required_when_the_circular_demands_a_unique_one(): void
    {
        $student = User::factory()->create();
        $application = $this->startApplication($student, $this->openOffering([], ['unique_birth_id' => true]));

        $this->saveStep($student, $application, 1, $this->step1(['brn' => '']))->assertSessionHasErrors('brn');
        $this->saveStep($student, $application, 1, $this->step1())->assertSessionHasNoErrors();
    }

    public function test_an_incomplete_application_cannot_be_submitted(): void
    {
        $student = User::factory()->create();
        $application = $this->startApplication($student, $this->openOffering());
        $this->saveStep($student, $application, 1, $this->step1());

        $this->submit($student, $application)->assertSessionHasErrors();
        $this->assertSame('draft', $application->fresh()->status);
        $this->assertNull($application->fresh()->application_no);
    }

    public function test_the_declaration_is_required(): void
    {
        $student = User::factory()->create();
        $application = $this->startApplication($student, $this->openOffering());
        $this->completeSteps($student, $application);

        $this->submit($student, $application, [])->assertSessionHasErrors('declaration');
        $this->assertSame('draft', $application->fresh()->status);
    }

    public function test_submitting_assigns_numbers_snapshots_the_fee_and_is_idempotent(): void
    {
        $student = User::factory()->create();
        $application = $this->startApplication($student, $this->openOffering());
        $this->completeSteps($student, $application);

        $this->submit($student, $application)->assertRedirect(route('student.applications.show', $application));

        $application->refresh();
        $this->assertSame('submitted', $application->status);
        $this->assertMatchesRegularExpression('/^AP\d{2}\d{6}$/', $application->application_no);
        $this->assertSame(53000, $application->payable_minor);
        $this->assertSame(53000, $application->fee_snapshot['total']);
        $this->assertSame(['Application form fee' => 50000, 'Bank / gateway charge' => 3000], $application->fee_snapshot['lines']);
        $this->assertNotNull($application->consent_at);
        $this->assertNotNull($application->submitted_at);

        $number = $application->application_no;
        $this->submit($student, $application)->assertRedirect();
        $this->submit($student, $application)->assertRedirect();

        $application->refresh();
        $this->assertSame($number, $application->application_no);
        $this->assertSame(1, $application->histories()->where('to_status', 'submitted')->count());
    }

    public function test_application_numbers_are_sequential_per_circular(): void
    {
        $offering = $this->openOffering();
        $first = $this->submittedApplication(null, $offering);
        $second = $this->submittedApplication(null, $offering);

        $this->assertStringEndsWith('000001', $first->application_no);
        $this->assertStringEndsWith('000002', $second->application_no);
    }

    public function test_a_submitted_application_is_locked_for_the_student(): void
    {
        $student = User::factory()->create();
        $application = $this->submittedApplication($student);

        $this->actingAs($student)->get(route('student.applications.step', [$application, 1]))->assertRedirect(route('student.applications.show', $application));
        $this->saveStep($student, $application, 1, $this->step1(['name' => 'CHANGED']))->assertForbidden();
        $this->assertSame('AYUSHI MAZUMDER', $application->fresh()->profile->name);

        $this->actingAs($student)->get(route('student.applications.show', $application))->assertOk()->assertSee('AYUSHI MAZUMDER')->assertSee('Submitted');
    }

    public function test_a_draft_cannot_be_submitted_after_the_circular_closed(): void
    {
        $student = User::factory()->create();
        $offering = $this->openOffering([], ['opens_at' => now()->subDays(5), 'closes_at' => now()->addDay()]);
        $draft = $this->startApplication($student, $offering);
        $this->completeSteps($student, $draft);

        Carbon::setTestNow(now()->addDays(3));
        $this->submit($student, $draft)->assertSessionHasErrors('circular');
        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_the_correction_loop_lets_the_student_fix_and_resubmit(): void
    {
        $student = User::factory()->create();
        $application = $this->submittedApplication($student);
        $number = $application->application_no;
        $reviewer = User::factory()->staff('admission-officer')->create();

        $this->actingAs($reviewer)->patch(route('admin.applicants.status', $application), ['status' => 'correction_requested', 'remark' => 'Fix the address'])->assertRedirect();
        $this->assertSame('correction_requested', $application->fresh()->status);

        $this->actingAs($student)->get(route('student.applications.step', [$application, 3]))->assertOk()->assertSee('Fix the address');
        $this->saveStep($student, $application, 3, $this->step3(['addr_present' => 'New address, Chattogram']))->assertSessionHasNoErrors();
        $this->submit($student, $application)->assertRedirect(route('student.applications.show', $application));

        $application->refresh();
        $this->assertSame('submitted', $application->status);
        $this->assertSame($number, $application->application_no);
        $this->assertSame('New address, Chattogram', $application->addresses->firstWhere('kind', 'present')->address);
    }

    public function test_a_duplicate_birth_registration_number_is_blocked_when_the_circular_requires_uniqueness(): void
    {
        $offering = $this->openOffering([], ['unique_birth_id' => true]);
        $this->submittedApplication(null, $offering);

        $second = User::factory()->create();
        $application = $this->startApplication($second, $offering);
        $this->completeSteps($second, $application);

        $this->submit($second, $application)->assertSessionHasErrors('brn');
        $this->assertSame('draft', $application->fresh()->status);
        $this->assertSame(1, Application::whereNotNull('dedupe_key')->count());
    }

    public function test_the_same_birth_registration_number_is_fine_when_uniqueness_is_not_required(): void
    {
        $offering = $this->openOffering();
        $this->submittedApplication(null, $offering);
        $second = $this->submittedApplication(null, $offering);

        $this->assertSame('submitted', $second->status);
        $this->assertSame(0, Application::whereNotNull('dedupe_key')->count());
    }

    public function test_required_documents_must_be_uploaded_before_submitting(): void
    {
        $offering = $this->openOffering(['documents' => [$this->docId('birth_certificate')]]);
        $student = User::factory()->create();
        $application = $this->startApplication($student, $offering);
        $this->completeSteps($student, $application);

        $this->submit($student, $application)->assertSessionHasErrors('documents');
        $this->assertSame('draft', $application->fresh()->status);
    }
}
