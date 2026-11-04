<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPayments;
use Tests\Concerns\CreatesStaff;
use Tests\TestCase;

class ManualPaymentTest extends TestCase
{
    use BuildsPayments, CreatesStaff, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activateMethod('manual');
    }

    private function submitManual($student, $application, array $data = [])
    {
        return $this->actingAs($student)->post(route('student.payments.manual', $application), array_merge(['sender_no' => '01711111111', 'manual_trx_id' => 'ABC123XYZ'], $data));
    }

    public function test_the_payment_page_shows_the_amount_and_the_instructions(): void
    {
        $application = $this->unpaidApplication();

        $this->actingAs($application->user)->get(route('student.payments.create', $application))
            ->assertOk()->assertSee('530.00')->assertSee('Application form fee')->assertSee('Send the fee to 01700000000')->assertSee('Transaction ID');
    }

    public function test_the_student_page_says_so_when_no_method_is_switched_on(): void
    {
        PaymentMethod::query()->update(['is_active' => false]);
        $application = $this->unpaidApplication();

        $this->actingAs($application->user)->get(route('student.payments.create', $application))->assertOk()->assertSee('not available right now');
        $this->submitManual($application->user, $application)->assertSessionHasErrors('payment');
        $this->assertSame(0, Payment::count());
    }

    public function test_manual_details_create_a_pending_payment_with_a_server_side_amount(): void
    {
        $application = $this->unpaidApplication();

        $this->submitManual($application->user, $application, ['amount' => 1, 'amount_minor' => 1, 'status' => 'success'])
            ->assertRedirect(route('student.applications.show', $application));

        $payment = Payment::firstOrFail();
        $this->assertSame('manual_pending', $payment->status);
        $this->assertSame('manual', $payment->gateway);
        $this->assertSame(53000, $payment->amount_minor);
        $this->assertSame('01711111111', $payment->sender_no);
        $this->assertSame('ABC123XYZ', $payment->manual_trx_id);
        $this->assertSame($application->id, $payment->application_id);
        $this->assertNull($payment->success_key);
        $this->assertDatabaseHas('payment_events', ['payment_id' => $payment->id, 'outcome' => 'manual_submitted']);
        $this->assertFalse($application->fresh()->isPaid());

        $this->actingAs($application->user)->get(route('student.payments.create', $application))->assertSee('waiting for verification');
    }

    public function test_the_details_are_validated(): void
    {
        $application = $this->unpaidApplication();

        $this->submitManual($application->user, $application, ['sender_no' => '123'])->assertSessionHasErrors('sender_no');
        $this->submitManual($application->user, $application, ['manual_trx_id' => 'ab'])->assertSessionHasErrors('manual_trx_id');
        $this->submitManual($application->user, $application, ['manual_trx_id' => 'bad id!'])->assertSessionHasErrors('manual_trx_id');
        $this->submitManual($application->user, $application, ['sender_no' => '+8801711111111'])->assertSessionHasNoErrors();

        $this->assertSame('01711111111', Payment::firstOrFail()->sender_no);
    }

    public function test_only_one_pending_payment_per_application_and_a_transaction_id_cannot_be_reused(): void
    {
        $first = $this->unpaidApplication();
        $this->submitManual($first->user, $first)->assertSessionHasNoErrors();
        $this->submitManual($first->user, $first, ['manual_trx_id' => 'OTHER99999'])->assertSessionHasErrors('payment');

        $second = $this->unpaidApplication();
        $this->submitManual($second->user, $second, ['manual_trx_id' => 'ABC123XYZ'])->assertSessionHasErrors('manual_trx_id');
        $this->assertSame(1, Payment::count());
    }

    public function test_nothing_can_be_paid_for_a_draft_and_other_students_cannot_pay(): void
    {
        $draft = $this->startApplication(User::factory()->create(), $this->openOffering());
        $this->submitManual($draft->user, $draft)->assertSessionHasErrors('payment');

        $application = $this->unpaidApplication();
        $this->submitManual(User::factory()->create(), $application)->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('student.payments.create', $application))->assertForbidden();
        $this->assertSame(0, Payment::count());
    }

    public function test_staff_approve_the_payment_and_the_application_gets_its_roll(): void
    {
        $application = $this->unpaidApplication(['roll_prefix' => 'bk']);
        $this->submitManual($application->user, $application);
        $payment = Payment::firstOrFail();
        $accountant = User::factory()->staff('accountant')->create();

        $this->actingAs($accountant)->patch(route('admin.payments.approve', $payment))->assertRedirect();

        $payment->refresh();
        $this->assertSame('success', $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame($accountant->id, $payment->verified_by);
        $this->assertSame('app:' . $application->id . ':application', $payment->success_key);
        $this->assertSame('ABC123XYZ', $payment->gateway_txn_id);
        $this->assertTrue($application->fresh()->isPaid());
        $this->assertSame('BK00001', $application->fresh()->roll->roll_no);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.succeeded', 'subject_id' => $payment->id]);

        // a second approval changes nothing
        $this->actingAs($accountant)->patch(route('admin.payments.approve', $payment))->assertSessionHasErrors('payment');
        $this->assertSame(1, Payment::where('status', 'success')->count());

        // and the student cannot pay again
        $this->actingAs($application->user)->get(route('student.payments.create', $application))->assertRedirect(route('student.applications.show', $application));
        $this->submitManual($application->user, $application, ['manual_trx_id' => 'NEWTRX1234'])->assertSessionHasErrors('payment');
    }

    public function test_a_rejection_needs_a_reason_and_lets_the_student_try_again(): void
    {
        $application = $this->unpaidApplication();
        $this->submitManual($application->user, $application);
        $payment = Payment::firstOrFail();
        $accountant = User::factory()->staff('accountant')->create();

        $this->actingAs($accountant)->patch(route('admin.payments.reject', $payment), ['remark' => ''])->assertSessionHasErrors('remark');
        $this->assertSame('manual_pending', $payment->fresh()->status);

        $this->actingAs($accountant)->patch(route('admin.payments.reject', $payment), ['remark' => 'Amount was too low'])->assertRedirect();
        $payment->refresh();
        $this->assertSame('manual_rejected', $payment->status);
        $this->assertSame('Amount was too low', $payment->reject_reason);
        $this->assertNull($application->fresh()->roll);

        $this->actingAs($application->user)->get(route('student.payments.create', $application))->assertSee('Amount was too low');
        $this->submitManual($application->user, $application, ['manual_trx_id' => 'ABC123XYZ'])->assertSessionHasNoErrors();
        $this->assertSame(2, Payment::count());
    }

    public function test_only_staff_with_the_verify_permission_can_decide(): void
    {
        $application = $this->unpaidApplication();
        $this->submitManual($application->user, $application);
        $payment = Payment::firstOrFail();

        $this->actingAs($application->user)->patch(route('admin.payments.approve', $payment))->assertForbidden();
        $this->actingAs($this->staffWith(['payments.view']))->patch(route('admin.payments.approve', $payment))->assertForbidden();
        $this->actingAs($this->staffWith(['payments.view']))->patch(route('admin.payments.reject', $payment), ['remark' => 'x'])->assertForbidden();
        $this->actingAs(User::factory()->staff('viewer')->create())->patch(route('admin.payments.approve', $payment))->assertForbidden();

        $this->assertSame('manual_pending', $payment->fresh()->status);
    }
}
