<?php

namespace Tests\Feature\Payments;

use App\Models\Application;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPayments;
use Tests\Concerns\CreatesStaff;
use Tests\TestCase;

class PaymentAdminTest extends TestCase
{
    use BuildsPayments, CreatesStaff, RefreshDatabase;

    private Application $paidApp;
    private Application $pendingApp;
    private Payment $success;
    private Payment $pending;
    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountant = User::factory()->staff('accountant')->create();

        $offering = $this->openOffering();
        $this->paidApp = $this->submittedApplication(null, $offering, [1 => ['name' => 'PAID KID']]);
        $this->pendingApp = $this->submittedApplication(null, $offering, [1 => ['name' => 'WAITING KID']]);

        $this->success = $this->makePayment($this->paidApp, [
            'status' => 'success', 'paid_at' => now(), 'manual_trx_id' => 'TRXPAID001', 'gateway_txn_id' => 'TRXPAID001',
            'success_key' => 'app:' . $this->paidApp->id . ':application',
        ]);
        $this->pending = $this->makePayment($this->pendingApp, ['manual_trx_id' => 'TRXWAIT002']);
    }

    public function test_the_list_has_tabs_for_successful_pending_and_failed_payments(): void
    {
        $failed = $this->makePayment($this->pendingApp, ['status' => 'failed', 'gateway' => 'shurjopay', 'manual_trx_id' => null, 'gateway_ref' => 'sp-x', 'failure_reason' => 'Declined']);
        $list = fn (array $q = []) => $this->actingAs($this->accountant)->get(route('admin.payments.index', $q));

        $list()->assertOk()->assertSee('PAID KID')->assertDontSee('WAITING KID');                         // Successful is the default tab
        $list(['tab' => 'pending'])->assertSee('WAITING KID')->assertDontSee('PAID KID');
        $list(['tab' => 'failed'])->assertSee($failed->merchant_ref)->assertDontSee($this->pending->merchant_ref);
        $list(['tab' => 'all'])->assertSee('PAID KID')->assertSee('WAITING KID')->assertSee($failed->merchant_ref);
        $list()->assertViewHas('counts', fn ($counts) => $counts['success'] === 1 && $counts['pending'] === 1 && $counts['failed'] === 1 && $counts['all'] === 3);
    }

    public function test_the_list_can_be_searched_and_filtered_on_the_server(): void
    {
        $list = fn (array $q) => $this->actingAs($this->accountant)->get(route('admin.payments.index', ['tab' => 'all'] + $q));

        $list(['q' => 'TRXPAID001'])->assertSee('PAID KID')->assertDontSee('WAITING KID');
        $list(['q' => 'waiting'])->assertSee('WAITING KID')->assertDontSee('PAID KID');
        $list(['q' => (string) $this->paidApp->tracking_no])->assertSee('PAID KID')->assertDontSee('WAITING KID');
        $list(['gateway' => 'shurjopay'])->assertDontSee('PAID KID')->assertDontSee('WAITING KID');
        $list(['gateway' => 'manual'])->assertSee('PAID KID')->assertSee('WAITING KID');
        $list(['from' => now()->addDay()->format('Y-m-d')])->assertDontSee('PAID KID');
        $list(['gateway' => 'bitcoin'])->assertSessionHasErrors('gateway');
        $this->actingAs($this->accountant)->get(route('admin.payments.index', ['tab' => 'nonsense']))->assertSessionHasErrors('tab');
    }

    public function test_the_list_and_details_need_the_view_permission(): void
    {
        $this->actingAs($this->staffWith(['payments.view']))->get(route('admin.payments.index'))->assertOk();
        $this->actingAs($this->staffWith(['payments.view']))->get(route('admin.payments.show', $this->pending))->assertOk();
        $this->actingAs($this->staffWith(['dashboard.view']))->get(route('admin.payments.index'))->assertForbidden();
        $this->actingAs($this->staffWith(['dashboard.view']))->get(route('admin.payments.show', $this->pending))->assertForbidden();
        $this->actingAs($this->paidApp->user)->get(route('admin.payments.index'))->assertForbidden();

        auth()->logout();
        $this->get(route('admin.payments.index'))->assertRedirect(route('login'));
    }

    public function test_the_detail_page_shows_the_event_log_and_the_decision_buttons_only_to_verifiers(): void
    {
        \App\Models\PaymentEvent::log($this->pending, 'manual', 'manual_submitted');

        $this->actingAs($this->accountant)->get(route('admin.payments.show', $this->pending))
            ->assertOk()->assertSee('manual_submitted')->assertSee('Approve')->assertSee('Reason to reject')->assertSee('TRXWAIT002');

        $this->actingAs($this->staffWith(['payments.view']))->get(route('admin.payments.show', $this->pending))
            ->assertOk()->assertDontSee('Reason to reject');
    }

    public function test_the_receipt_is_for_successful_payments_and_their_owner_or_staff(): void
    {
        $url = route('payments.receipt', $this->success);

        $this->actingAs($this->paidApp->user)->get($url)->assertOk()->assertSee('PAYMENT RECEIPT')->assertSee($this->success->merchant_ref)->assertSee('PAID KID')->assertSee('530.00');
        $this->actingAs($this->accountant)->get($url)->assertOk();
        $this->actingAs($this->pendingApp->user)->get($url)->assertForbidden();
        $this->actingAs($this->staffWith(['dashboard.view']))->get($url)->assertForbidden();
        $this->actingAs($this->pendingApp->user)->get(route('payments.receipt', $this->pending))->assertNotFound();

        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));
    }

    public function test_the_student_panel_offers_payment_only_while_a_method_is_switched_on(): void
    {
        $student = $this->pendingApp->user;

        $this->actingAs($student)->get(route('student.home'))->assertOk()->assertDontSee('Pay Now');

        $this->activateMethod('manual');
        $this->pending->delete();
        $this->actingAs($student)->get(route('student.home'))->assertSee('Pay Now')->assertSee('Unpaid');
        $this->actingAs($student)->get(route('student.applications.show', $this->pendingApp))->assertSee('Pay Now');

        $this->actingAs($this->paidApp->user)->get(route('student.home'))->assertSee('Paid')->assertSee('Receipt')->assertDontSee('Pay Now');
    }

    public function test_the_applicant_detail_lists_the_payments(): void
    {
        $this->actingAs($this->accountant)->get(route('admin.applicants.show', $this->paidApp))->assertOk()->assertSee('Payments')->assertSee('Successful');
        $this->actingAs(User::factory()->staff('admission-officer')->create())->get(route('admin.applicants.index'))->assertOk()->assertSee('Paid')->assertSee('Unpaid');
    }

    public function test_the_dashboard_shows_payment_totals(): void
    {
        $this->actingAs(User::factory()->staff()->create())->get(route('admin.dashboard'))
            ->assertOk()->assertSee('Successful payments')->assertSee('Pending payments')
            ->assertViewHas('pay', fn ($pay) => (int) $pay['success']->total === 1 && (int) $pay['manual_pending']->total === 1);

        $this->actingAs($this->staffWith(['dashboard.view']))->get(route('admin.dashboard'))->assertOk()->assertDontSee('Successful payments');
    }

    public function test_payments_are_never_mass_assignable(): void
    {
        $payment = new Payment(['status' => 'success', 'amount_minor' => 1, 'application_id' => 1, 'success_key' => 'x']);

        $this->assertNull($payment->status);
        $this->assertNull($payment->amount_minor);
        $this->assertNull($payment->success_key);
    }

    public function test_the_event_log_cannot_be_edited_or_deleted(): void
    {
        \App\Models\PaymentEvent::log($this->pending, 'manual', 'note');
        $event = \App\Models\PaymentEvent::firstOrFail();

        $this->expectException(\LogicException::class);
        $event->delete();
    }
}
