<?php

namespace Tests\Feature\Admission;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsApplications;
use Tests\Concerns\CreatesStaff;
use Tests\TestCase;

class ApplicantReviewTest extends TestCase
{
    use BuildsApplications, CreatesStaff, RefreshDatabase;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->officer = User::factory()->staff('admission-officer')->create();
    }

    public function test_the_list_shows_submitted_applications_but_never_drafts(): void
    {
        $offering = $this->openOffering();
        $this->submittedApplication(null, $offering, [1 => ['name' => 'SUBMITTED KID']]);
        $draftOwner = User::factory()->create();
        $draft = $this->startApplication($draftOwner, $offering);
        $this->saveStep($draftOwner, $draft, 1, $this->step1(['name' => 'DRAFT KID']));

        $this->actingAs($this->officer)->get(route('admin.applicants.index'))->assertOk()->assertSee('SUBMITTED KID')->assertDontSee('DRAFT KID');
        $this->actingAs($this->officer)->get(route('admin.applicants.index', ['status' => 'draft']))->assertSessionHasErrors('status');
    }

    public function test_search_filters_sorting_and_pagination_run_on_the_server(): void
    {
        $offering = $this->openOffering(['programs' => [$this->classId('One'), $this->classId('Two')]]);
        $one = $offering->circular->offerings()->where('school_class_id', $this->classId('One'))->firstOrFail();
        $two = $offering->circular->offerings()->where('school_class_id', $this->classId('Two'))->firstOrFail();

        $alpha = $this->submittedApplication(null, $one, [1 => ['name' => 'ALPHA KID', 'phone1' => '01711111111']]);
        $beta = $this->submittedApplication(null, $two, [1 => ['name' => 'BETA KID', 'gender' => 'Male', 'phone1' => '01722222222']]);
        $beta->forceFill(['status' => 'approved'])->save();
        $list = fn (array $q) => $this->actingAs($this->officer)->get(route('admin.applicants.index', $q));

        $list([])->assertSee('ALPHA KID')->assertSee('BETA KID');
        $list(['q' => 'alpha'])->assertSee('ALPHA KID')->assertDontSee('BETA KID');
        $list(['q' => '01722222222'])->assertSee('BETA KID')->assertDontSee('ALPHA KID');
        $list(['q' => (string) $alpha->tracking_no])->assertSee('ALPHA KID')->assertDontSee('BETA KID');
        $list(['q' => $beta->application_no])->assertSee('BETA KID')->assertDontSee('ALPHA KID');
        $list(['school_class_id' => $this->classId('Two')])->assertSee('BETA KID')->assertDontSee('ALPHA KID');
        $list(['gender' => 'Male'])->assertSee('BETA KID')->assertDontSee('ALPHA KID');
        $list(['status' => 'approved'])->assertSee('BETA KID')->assertDontSee('ALPHA KID');
        $list(['from' => now()->addDay()->format('Y-m-d')])->assertDontSee('ALPHA KID')->assertDontSee('BETA KID');

        $html = $list(['sort' => 'name', 'dir' => 'asc'])->getContent();
        $this->assertLessThan(strpos($html, 'BETA KID'), strpos($html, 'ALPHA KID'));
        $html = $list(['sort' => 'name', 'dir' => 'desc'])->getContent();
        $this->assertLessThan(strpos($html, 'ALPHA KID'), strpos($html, 'BETA KID'));

        $list(['sort' => 'password'])->assertSessionHasErrors('sort');
        $list(['dir' => 'sideways'])->assertSessionHasErrors('dir');
    }

    public function test_the_list_is_paginated(): void
    {
        $offering = $this->openOffering();
        $mass = [];
        for ($i = 0; $i < 22; $i++) {
            $mass[] = $this->submittedApplication(null, $offering);
        }

        $this->actingAs($this->officer)->get(route('admin.applicants.index'))->assertOk()->assertSee('22 applications');
        $second = $this->actingAs($this->officer)->get(route('admin.applicants.index', ['page' => 2]))->assertOk();
        $this->assertSame(2, substr_count($second->getContent(), '>View</a>'));
        $this->assertCount(22, $mass);
    }

    public function test_only_staff_with_the_permission_can_open_the_list_and_details(): void
    {
        $application = $this->submittedApplication();

        $this->actingAs(User::factory()->create())->get(route('admin.applicants.index'))->assertForbidden();
        $this->actingAs($this->staffWith(['dashboard.view']))->get(route('admin.applicants.index'))->assertForbidden();
        $this->actingAs($this->staffWith(['dashboard.view']))->get(route('admin.applicants.show', $application))->assertForbidden();
        $this->actingAs($this->officer)->get(route('admin.applicants.show', $application))->assertOk()->assertSee('AYUSHI MAZUMDER');

        auth()->logout();
        $this->get(route('admin.applicants.index'))->assertRedirect(route('login'));
    }

    public function test_a_draft_cannot_be_opened_by_staff(): void
    {
        $student = User::factory()->create();
        $draft = $this->startApplication($student, $this->openOffering());

        $this->actingAs($this->officer)->get(route('admin.applicants.show', $draft))->assertForbidden();
        $this->actingAs($this->officer)->patch(route('admin.applicants.status', $draft), ['status' => 'approved'])->assertForbidden();
    }

    public function test_identity_numbers_are_masked_unless_the_user_may_see_them(): void
    {
        $application = $this->submittedApplication();

        $masked = $this->actingAs($this->staffWith(['applicant-list.view']))->get(route('admin.applicants.show', $application))->assertOk();
        $masked->assertDontSee('20181591612150432')->assertDontSee('1592812508314')->assertSee('0432')->assertSee('hidden for your user type');

        $this->actingAs(User::factory()->staff('admin')->create())->get(route('admin.applicants.show', $application))
            ->assertSee('20181591612150432')->assertSee('1592812508314');
    }

    public function test_status_changes_follow_the_allowed_transitions_and_are_recorded(): void
    {
        $application = $this->submittedApplication();
        $patch = fn (string $status, ?string $remark = null) => $this->actingAs($this->officer)
            ->patch(route('admin.applicants.status', $application), ['status' => $status, 'remark' => $remark]);

        $patch('under_review')->assertRedirect();
        $this->assertSame('under_review', $application->fresh()->status);

        $patch('approved')->assertRedirect();
        $this->assertSame('approved', $application->fresh()->status);

        $patch('rejected', 'late')->assertSessionHasErrors('status');          // approved -> rejected is not allowed directly
        $patch('draft')->assertSessionHasErrors('status');
        $patch('submitted')->assertSessionHasErrors('status');
        $patch('not-a-status')->assertSessionHasErrors('status');
        $this->assertSame('approved', $application->fresh()->status);

        $histories = $application->histories()->pluck('to_status')->all();
        $this->assertSame(['draft', 'submitted', 'under_review', 'approved'], $histories);
        $this->assertSame($this->officer->id, $application->histories()->where('to_status', 'approved')->first()->actor_id);
        $this->assertSame(2, AuditLog::where('action', 'application.status_changed')->count());
    }

    public function test_a_remark_is_required_to_reject_or_request_a_correction(): void
    {
        $application = $this->submittedApplication();
        $patch = fn (string $status, ?string $remark = null) => $this->actingAs($this->officer)
            ->patch(route('admin.applicants.status', $application), ['status' => $status, 'remark' => $remark]);

        $patch('rejected')->assertSessionHasErrors('remark');
        $patch('correction_requested', '   ')->assertSessionHasErrors('remark');
        $this->assertSame('submitted', $application->fresh()->status);

        $patch('rejected', 'Not eligible')->assertRedirect();
        $this->assertSame('rejected', $application->fresh()->status);
        $this->assertSame('Not eligible', $application->histories()->where('to_status', 'rejected')->first()->remark);

        // the student sees the remark
        $this->actingAs($application->user)->get(route('student.applications.show', $application))->assertSee('Not eligible');
    }

    public function test_only_staff_with_the_status_permission_can_change_it(): void
    {
        $application = $this->submittedApplication();

        $this->actingAs($this->staffWith(['applicant-list.view']))->patch(route('admin.applicants.status', $application), ['status' => 'approved'])->assertForbidden();
        $this->actingAs($this->staffWith(['applicant-list.view']))->get(route('admin.applicants.show', $application))->assertDontSee('Update status');
        $this->actingAs($application->user)->patch(route('admin.applicants.status', $application), ['status' => 'approved'])->assertForbidden();
        $this->assertSame('submitted', $application->fresh()->status);
    }

    public function test_the_status_history_cannot_be_edited_or_deleted(): void
    {
        $application = $this->submittedApplication();
        $history = $application->histories()->firstOrFail();

        $this->expectException(\LogicException::class);
        $history->delete();
    }

    public function test_the_dashboard_counts_come_from_the_database(): void
    {
        $offering = $this->openOffering();
        $a = $this->submittedApplication(null, $offering);
        $this->submittedApplication(null, $offering);
        $a->forceFill(['status' => 'approved'])->save();
        $draftOwner = User::factory()->create();
        $this->startApplication($draftOwner, $offering);

        $this->actingAs(User::factory()->staff()->create())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('submitted', 2)
            ->assertViewHas('drafts', 1)
            ->assertViewHas('today', 2)
            ->assertSee('Applications by class');
    }
}
