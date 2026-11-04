<?php

namespace Tests\Feature\Payments;

use App\Models\Application;
use App\Models\ApplicationRoll;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\User;
use App\Services\Gateways\GatewayResult;
use App\Services\Gateways\ShurjoPayClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPayments;
use Tests\Concerns\CreatesStaff;
use Tests\TestCase;

class ShurjoPayTest extends TestCase
{
    use BuildsPayments, CreatesStaff, RefreshDatabase;

    private array $override = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->activateMethod('shurjopay');
        // the verification endpoint answers for whichever order id is asked about
        $this->fakeShurjoPay(fn ($request) => $this->verification(Payment::where('gateway_ref', $request['order_id'])->firstOrFail(), $this->override));
    }

    /** @return array{0: Application, 1: Payment} */
    private function begin(): array
    {
        $application = $this->unpaidApplication();

        return [$application, $this->startGatewayPayment($application->user, $application)];
    }

    private function comeBack(Application $application, Payment $payment, string $route = 'return', array $extra = [])
    {
        return $this->actingAs($application->user)->get(route('student.payments.shurjopay.' . $route, ['order_id' => $payment->gateway_ref] + $extra));
    }

    public function test_starting_registers_the_order_and_redirects_to_the_gateway(): void
    {
        $application = $this->unpaidApplication();

        $this->actingAs($application->user)->post(route('student.payments.gateway', $application))
            ->assertRedirect('https://sandbox.shurjopayment.com/checkout/abc1');

        $payment = $application->payments()->firstOrFail();
        $this->assertSame('pending', $payment->status);
        $this->assertSame('shurjopay', $payment->gateway);
        $this->assertSame('sp-order-1', $payment->gateway_ref);
        $this->assertSame(53000, $payment->amount_minor);
        $this->assertStringStartsWith('ADM', $payment->merchant_ref);

        Http::assertSent(fn ($request) => $request->url() === $this->sandbox . '/secret-pay'
            && $request['amount'] === '530.00'
            && $request['currency'] === 'BDT'
            && $request['order_id'] === $payment->merchant_ref
            && $request['prefix'] === 'ADM'
            && $request['return_url'] === route('student.payments.shurjopay.return')
            && $request['cancel_url'] === route('student.payments.shurjopay.cancel')
            && $request->hasHeader('Authorization', 'Bearer tok123'));
    }

    public function test_the_token_is_requested_once_and_reused(): void
    {
        [$application, $payment] = $this->begin();
        $this->comeBack($application, $payment);

        $this->assertCount(1, Http::recorded(fn ($request) => str_ends_with($request->url(), '/get_token')));
    }

    public function test_missing_credentials_stop_the_payment_before_anything_is_sent(): void
    {
        $application = $this->unpaidApplication();
        config(['payments.shurjopay.password' => null]);

        $this->actingAs($application->user)->post(route('student.payments.gateway', $application))->assertSessionHasErrors('payment');

        $this->assertSame(0, Payment::count());
        Http::assertNothingSent();
    }

    public function test_a_gateway_error_marks_the_payment_failed_and_tells_the_student(): void
    {
        $this->fakeShurjoPay(null, 500);
        $application = $this->unpaidApplication();

        $this->actingAs($application->user)->post(route('student.payments.gateway', $application))->assertSessionHasErrors('payment');

        $payment = $application->payments()->firstOrFail();
        $this->assertSame('failed', $payment->status);
        $this->assertDatabaseHas('payment_events', ['payment_id' => $payment->id, 'outcome' => 'initiate_error']);
    }

    public function test_a_checkout_link_that_is_not_shurjopay_is_never_followed(): void
    {
        $this->fakeShurjoPay(null, 200, 'https://evil.example/checkout');
        $application = $this->unpaidApplication();

        $response = $this->actingAs($application->user)->post(route('student.payments.gateway', $application));

        $response->assertSessionHasErrors('payment');
        $this->assertStringNotContainsString('evil.example', (string) $response->headers->get('Location'));
        $this->assertSame('failed', $application->payments()->firstOrFail()->status);
    }

    public function test_coming_back_verifies_with_the_gateway_and_records_success(): void
    {
        [$application, $payment] = $this->begin();

        $this->comeBack($application, $payment)->assertRedirect(route('student.applications.show', $application));

        $payment->refresh();
        $this->assertSame('success', $payment->status);
        $this->assertSame('BNKTRX123', $payment->gateway_txn_id);
        $this->assertSame('bKash', $payment->channel);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('app:' . $application->id . ':application', $payment->success_key);
        $this->assertTrue($application->fresh()->isPaid());
        $this->assertSame('00001', $application->fresh()->roll->roll_no);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.succeeded']);
    }

    public function test_card_and_customer_details_from_the_gateway_are_never_stored(): void
    {
        [$application, $payment] = $this->begin();
        $this->comeBack($application, $payment);

        $event = PaymentEvent::where('payment_id', $payment->id)->where('outcome', 'success')->firstOrFail();

        $this->assertSame('BNKTRX123', $event->payload['bank_trx_id']);
        foreach (['card_number', 'card_holder_name', 'phone_no', 'email'] as $secret) {
            $this->assertArrayNotHasKey($secret, $event->payload);
        }
        $this->assertStringNotContainsString('4111111111111111', json_encode(PaymentEvent::all()->toArray()));
    }

    public function test_the_same_confirmation_arriving_twice_is_counted_once(): void
    {
        [$application, $payment] = $this->begin();

        $this->comeBack($application, $payment);
        $this->comeBack($application, $payment);
        $this->comeBack($application, $payment, 'cancel');

        $this->assertSame(1, Payment::where('status', 'success')->count());
        $this->assertSame(1, ApplicationRoll::count());
        $this->assertSame(1, PaymentEvent::where('payment_id', $payment->id)->where('outcome', 'success')->count());
        $this->assertSame(0, PaymentEvent::where('payment_id', $payment->id)->whereIn('outcome', ['failed', 'verification_mismatch'])->count());
    }

    public function test_a_confirmation_that_does_not_match_the_payment_is_refused(): void
    {
        foreach ([['amount' => '1.0000'], ['currency' => 'USD'], ['customer_order_id' => 'SOMEONE-ELSE'], ['order_id' => 'another-order']] as $wrong) {
            $this->override = $wrong;
            [$application, $payment] = $this->begin();

            $this->comeBack($application, $payment);

            $payment->refresh();
            $this->assertSame('failed', $payment->status, json_encode($wrong));
            $this->assertStringContainsString('Verification mismatch', $payment->failure_reason);
            $this->assertNull($application->fresh()->roll);
            $this->assertFalse($application->fresh()->isPaid());
            $this->assertDatabaseHas('audit_logs', ['action' => 'payment.mismatch', 'subject_id' => $payment->id]);
        }
    }

    public function test_the_browser_saying_success_proves_nothing(): void
    {
        $this->override = ['sp_code' => '1002', 'sp_message' => 'Cancelled'];
        [$application, $payment] = $this->begin();

        $this->comeBack($application, $payment, 'return', ['status' => 'success', 'sp_code' => '1000', 'transaction_status' => 'Completed'])
            ->assertRedirect(route('student.payments.create', $application));

        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertFalse($application->fresh()->isPaid());
    }

    public function test_cancelled_and_declined_payments_can_be_tried_again(): void
    {
        $this->override = ['sp_code' => '1002'];
        [$application, $payment] = $this->begin();
        $this->comeBack($application, $payment, 'cancel')->assertRedirect(route('student.payments.create', $application))->assertSessionHas('error');
        $this->assertSame('cancelled', $payment->fresh()->status);

        $this->actingAs($application->user)->post(route('student.payments.gateway', $application))->assertRedirect();
        $second = $application->payments()->first();
        $this->assertNotSame($payment->id, $second->id);

        $this->override = ['sp_code' => '1001'];
        $this->comeBack($application, $second)->assertRedirect(route('student.payments.create', $application));
        $this->assertSame('failed', $second->fresh()->status);
        $this->assertNull($application->fresh()->roll);
    }

    public function test_an_unknown_answer_keeps_the_payment_pending_and_a_gateway_outage_changes_nothing(): void
    {
        $this->override = ['sp_code' => '1099'];
        [$application, $payment] = $this->begin();
        $this->comeBack($application, $payment)->assertRedirect(route('student.applications.show', $application));
        $this->assertSame('pending', $payment->fresh()->status);

        $this->fakeShurjoPay(fn () => null);
        $this->comeBack($application, $payment)->assertRedirect();
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertDatabaseHas('payment_events', ['payment_id' => $payment->id, 'outcome' => 'verify_error']);
    }

    public function test_only_the_owner_can_come_back_for_a_payment(): void
    {
        [$application, $payment] = $this->begin();

        $this->actingAs(User::factory()->create())->get(route('student.payments.shurjopay.return', ['order_id' => $payment->gateway_ref]))->assertNotFound();
        $this->actingAs($application->user)->get(route('student.payments.shurjopay.return', ['order_id' => 'unknown-order']))->assertNotFound();
        $this->actingAs($application->user)->get(route('student.payments.shurjopay.return'))->assertSessionHasErrors('order_id');
        $this->actingAs(User::factory()->staff()->create())->get(route('student.payments.shurjopay.return', ['order_id' => $payment->gateway_ref]))->assertForbidden();

        auth()->logout();
        $this->get(route('student.payments.shurjopay.return', ['order_id' => $payment->gateway_ref]))->assertRedirect(route('login'));

        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_a_second_successful_payment_for_the_same_application_is_never_counted(): void
    {
        $application = $this->unpaidApplication();
        $this->actingAs($application->user)->post(route('student.payments.gateway', $application));
        $this->actingAs($application->user)->post(route('student.payments.gateway', $application));
        [$second, $first] = [$application->payments()->first(), $application->payments()->skip(1)->first()];

        $this->comeBack($application, $first);
        $this->comeBack($application, $second);

        $this->assertSame('success', $first->fresh()->status);
        $this->assertSame('failed', $second->fresh()->status);
        $this->assertStringContainsString('Duplicate payment', $second->fresh()->failure_reason);
        $this->assertSame(1, Payment::where('status', 'success')->count());
        $this->assertSame(1, ApplicationRoll::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.duplicate_success']);
    }

    public function test_the_reconcile_job_settles_payments_the_customer_never_came_back_for(): void
    {
        [$oldApplication, $old] = $this->begin();
        $this->travel(10)->minutes();
        [$newApplication, $new] = $this->begin();

        $this->artisan('payments:reconcile')->assertSuccessful();

        $this->assertSame('success', $old->fresh()->status);
        $this->assertSame('pending', $new->fresh()->status, 'a payment younger than five minutes is left alone');
        $this->assertDatabaseHas('payment_events', ['payment_id' => $old->id, 'source' => 'reconcile', 'outcome' => 'success']);
    }

    public function test_staff_can_ask_the_gateway_for_a_pending_payment(): void
    {
        [$application, $payment] = $this->begin();

        $this->actingAs(User::factory()->staff('accountant')->create())->patch(route('admin.payments.recheck', $payment))->assertRedirect();
        $this->assertSame('success', $payment->fresh()->status);

        $this->actingAs($this->staffWith(['payments.view']))->patch(route('admin.payments.recheck', $payment))->assertForbidden();
    }

    public function test_the_client_understands_both_answer_shapes_and_maps_the_codes(): void
    {
        $this->shurjoConfig();
        $record = ['order_id' => 'sp1', 'customer_order_id' => 'ADM1', 'amount' => '530.0000', 'currency' => 'BDT', 'sp_code' => '1000', 'bank_trx_id' => 'T1', 'method' => 'Card'];
        $client = app(ShurjoPayClient::class);

        foreach ([[$record], $record] as $shape) {
            $this->freshHttp();
            Http::fake([$this->sandbox . '/get_token' => Http::response(['token' => 't', 'store_id' => '1']), $this->sandbox . '/verification' => Http::response($shape)]);
            $result = $client->verify('sp1');
            $this->assertSame(GatewayResult::SUCCESS, $result->status);
            $this->assertSame(53000, $result->amountMinor);
            $this->assertSame('ADM1', $result->merchantRef);
        }

        foreach (['1001' => GatewayResult::FAILED, '1002' => GatewayResult::CANCELLED, '1500' => GatewayResult::PENDING] as $code => $expected) {
            $this->freshHttp();
            Http::fake([$this->sandbox . '/get_token' => Http::response(['token' => 't', 'store_id' => '1']), $this->sandbox . '/verification' => Http::response([$record + ['sp_code' => $code]])]);
            $this->assertSame($expected, $client->verify('sp1')->status, $code);
        }
    }

    public function test_the_live_address_is_used_only_when_live_mode_is_chosen_on_purpose(): void
    {
        $this->assertSame('https://sandbox.shurjopayment.com/api', app(ShurjoPayClient::class)->baseUrl());

        config(['payments.shurjopay.mode' => 'production']);
        $this->assertSame('https://sandbox.shurjopayment.com/api', app(ShurjoPayClient::class)->baseUrl());

        config(['payments.shurjopay.mode' => 'live']);
        $this->assertSame('https://engine.shurjopayment.com/api', app(ShurjoPayClient::class)->baseUrl());
    }
}
