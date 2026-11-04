<?php

namespace Tests\Feature\Payments;

use App\Models\Application;
use App\Models\ApplicationRoll;
use App\Models\Payment;
use App\Models\User;
use App\Services\RollAllocator;
use App\Support\NumberSequence;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPayments;
use Tests\TestCase;

class RollNumberTest extends TestCase
{
    use BuildsPayments, RefreshDatabase;

    private function allocate(Application $application): ApplicationRoll
    {
        return app(RollAllocator::class)->allocate($application);
    }

    public function test_counters_are_independent_and_never_repeat(): void
    {
        $this->assertSame(1, NumberSequence::next('a'));
        $this->assertSame(2, NumberSequence::next('a'));
        $this->assertSame(1, NumberSequence::next('b'));
        $this->assertSame(3, NumberSequence::next('a'));
    }

    public function test_a_roll_is_allocated_once_per_application(): void
    {
        $application = $this->unpaidApplication();

        $first = $this->allocate($application);
        $again = $this->allocate($application);

        $this->assertSame($first->id, $again->id);
        $this->assertSame('00001', $first->roll_no);
        $this->assertSame(1, ApplicationRoll::count());
    }

    public function test_the_prefix_and_the_sequence_are_combined(): void
    {
        $offering = $this->openOffering(['roll_prefix' => 'bk']);
        $first = $this->submittedApplication(null, $offering);
        $second = $this->submittedApplication(null, $offering);

        $this->assertSame('BK00001', $this->allocate($first)->roll_no);
        $this->assertSame('BK00002', $this->allocate($second)->roll_no);
    }

    public function test_class_version_numbering_restarts_for_each_version(): void
    {
        $offering = $this->openOffering(['roll_scope' => 'class_version']);
        $bengali = $this->submittedApplication(null, $offering);
        $english = $this->startApplication(User::factory()->create(), $offering, ['version_id' => $this->versionId('English')]);
        $this->completeSteps($english->user, $english);
        $this->submit($english->user, $english);

        $this->assertSame('00001', $this->allocate($bengali)->roll_no);
        $this->assertSame('00001', $this->allocate($english->fresh())->roll_no);
        $this->assertSame('00002', $this->allocate($this->submittedApplication(null, $offering))->roll_no, 'a second Bengali version student continues the Bengali count');
    }

    public function test_class_numbering_is_shared_by_all_versions(): void
    {
        $offering = $this->openOffering(['roll_scope' => 'class']);
        $bengali = $this->submittedApplication(null, $offering);
        $english = $this->startApplication(User::factory()->create(), $offering, ['version_id' => $this->versionId('English')]);
        $this->completeSteps($english->user, $english);
        $this->submit($english->user, $english);

        $this->assertSame('00001', $this->allocate($bengali)->roll_no);
        $this->assertSame('00002', $this->allocate($english->fresh())->roll_no);
    }

    public function test_class_version_group_numbering_restarts_for_each_group(): void
    {
        $offering = $this->openOffering([
            'programs' => [$this->classId('Eleven')], 'groups' => [$this->groupId('Science'), $this->groupId('Humanities')], 'roll_scope' => 'class_version_group',
        ]);
        $apps = [];
        foreach (['Science', 'Humanities', 'Science'] as $group) {
            $student = User::factory()->create();
            $application = $this->startApplication($student, $offering, ['study_group_id' => $this->groupId($group), 'version_id' => $this->versionId($apps ? 'English' : 'Bengali')]);
            $this->completeSteps($student, $application);
            $this->submit($student, $application);
            $apps[] = $application->fresh();
        }

        $this->assertSame('00001', $this->allocate($apps[0])->roll_no);   // Science, Bengali
        $this->assertSame('00001', $this->allocate($apps[1])->roll_no);   // Humanities, English
        $this->assertSame('00001', $this->allocate($apps[2])->roll_no);   // Science, English: its own (version, group) counter
    }

    public function test_two_applications_can_never_share_a_roll_in_the_same_scope(): void
    {
        $offering = $this->openOffering();
        $a = $this->allocate($this->submittedApplication(null, $offering));
        $b = $this->submittedApplication(null, $offering);

        $duplicate = new ApplicationRoll();
        $duplicate->application_id = $b->id;
        $duplicate->scope_key = $a->scope_key;
        $duplicate->sequence = $a->sequence;
        $duplicate->roll_no = $a->roll_no;

        $this->expectException(UniqueConstraintViolationException::class);
        $duplicate->save();
    }

    public function test_an_application_without_a_fee_is_settled_when_it_is_submitted(): void
    {
        $application = $this->submittedApplication(null, $this->openOffering(['form_fee' => '0', 'bank_charge' => '0']));

        $this->assertSame(0, $application->payable_minor);
        $payment = $application->payments()->firstOrFail();
        $this->assertSame('free', $payment->gateway);
        $this->assertSame(Payment::SUCCESS, $payment->status);
        $this->assertSame(0, $payment->amount_minor);
        $this->assertSame('00001', $application->roll->roll_no);
        $this->assertTrue($application->isPaid());

        // submitting again must not settle it a second time
        $this->submit($application->user, $application);
        $this->assertSame(1, $application->payments()->count());
        $this->assertSame(1, ApplicationRoll::count());

        $this->actingAs($application->user)->get(route('student.applications.show', $application))->assertOk()->assertSee('Paid')->assertSee('00001');
    }
}
