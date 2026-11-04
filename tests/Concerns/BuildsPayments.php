<?php

namespace Tests\Concerns;

use App\Models\Application;
use App\Models\CircularOffering;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\PaymentMethodService;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

trait BuildsPayments
{
    use BuildsApplications;

    protected string $sandbox = 'https://sandbox.shurjopayment.com/api';

    protected function setManualInstructions(string $text = "Send the fee to 01700000000 (bKash personal) and note the transaction id."): void
    {
        PaymentMethod::where('key', 'manual')->update(['instructions' => $text]);
    }

    protected function activateMethod(string $key): void
    {
        if ($key === 'manual') {
            $this->setManualInstructions();
        }
        if ($key === 'shurjopay') {
            $this->shurjoConfig();
        }

        app(PaymentMethodService::class)->activate(PaymentMethod::where('key', $key)->firstOrFail());
    }

    protected function shurjoConfig(): void
    {
        config(['payments.shurjopay.username' => 'api_user', 'payments.shurjopay.password' => 'api_secret_value', 'payments.shurjopay.prefix' => 'ADM', 'payments.shurjopay.mode' => 'sandbox']);
    }

    /** A clean HTTP client: stubs registered earlier would otherwise win over a new fake. */
    protected function freshHttp(): void
    {
        Http::swap(new HttpFactory());
    }

    /** Fakes the three ShurjoPay endpoints. $verify is a callable(Request): array|null or a fixed array. */
    protected function fakeShurjoPay($verify = null, int $initiateStatus = 200, ?string $checkout = null): void
    {
        $this->freshHttp();
        $counter = 0;

        Http::fake([
            $this->sandbox . '/get_token' => Http::response(['token' => 'tok123', 'store_id' => '1', 'token_type' => 'Bearer', 'sp_code' => '200']),
            $this->sandbox . '/secret-pay' => function () use (&$counter, $initiateStatus, $checkout) {
                $counter++;

                return $initiateStatus === 200
                    ? Http::response(['checkout_url' => $checkout ?? 'https://sandbox.shurjopayment.com/checkout/abc' . $counter, 'sp_order_id' => 'sp-order-' . $counter])
                    : Http::response(['message' => 'error'], $initiateStatus);
            },
            $this->sandbox . '/verification' => function ($request) use ($verify) {
                $payload = is_callable($verify) ? $verify($request) : $verify;

                return $payload === null ? Http::response([], 500) : Http::response([$payload]);
            },
        ]);
    }

    /** What ShurjoPay would answer for this payment (including card fields we must never store). */
    protected function verification(Payment $payment, array $override = []): array
    {
        return array_merge([
            'order_id' => $payment->gateway_ref,
            'customer_order_id' => $payment->merchant_ref,
            'amount' => number_format($payment->amount_minor / 100, 4, '.', ''),
            'currency' => 'BDT',
            'sp_code' => '1000',
            'sp_message' => 'Success',
            'bank_trx_id' => 'BNKTRX123',
            'method' => 'bKash',
            'transaction_status' => 'Completed',
            'card_number' => '4111111111111111',
            'card_holder_name' => 'SECRET HOLDER',
            'phone_no' => '01700000000',
            'email' => 'private@example.test',
        ], $override);
    }

    /** A submitted application that owes 530.00 (500 form fee + 30 bank charge). */
    protected function unpaidApplication(array $setup = []): Application
    {
        return $this->submittedApplication(null, $this->openOffering($setup));
    }

    protected function startGatewayPayment(User $student, Application $application): Payment
    {
        $this->actingAs($student)->post(route('student.payments.gateway', $application));

        return $application->payments()->firstOrFail();
    }

    protected function makePayment(Application $application, array $attributes = []): Payment
    {
        $payment = new Payment();
        $payment->uuid = (string) Str::uuid();
        $payment->application_id = $application->id;
        $payment->gateway = 'manual';
        $payment->purpose = 'application';
        $payment->merchant_ref = 'ADM' . strtoupper(Str::random(10));
        $payment->amount_minor = 53000;
        $payment->currency = 'BDT';
        $payment->status = Payment::MANUAL_PENDING;
        $payment->manual_trx_id = strtoupper(Str::random(10));
        $payment->sender_no = '01711111111';
        foreach ($attributes as $key => $value) {
            $payment->{$key} = $value;
        }
        $payment->save();

        return $payment;
    }
}
