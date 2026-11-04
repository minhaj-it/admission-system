<?php

namespace App\Services;

use App\Exceptions\PaymentGatewayException;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Gateways\GatewayResult;
use App\Services\Gateways\ShurjoPayClient;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private ShurjoPayClient $shurjopay, private RollAllocator $rolls)
    {
    }

    public function activeMethod(): ?PaymentMethod
    {
        return PaymentMethod::where('is_active', true)->first();
    }

    /** The amount always comes from the fee snapshot taken at submission, never from the browser. */
    public function canPay(Application $application): bool
    {
        return ! in_array($application->status, [Application::DRAFT, Application::REJECTED], true)
            && (int) $application->payable_minor > 0
            && ! $application->payments()->where('status', Payment::SUCCESS)->exists();
    }

    // ------------------------------------------------------------------ ShurjoPay

    /** Creates a pending payment, registers it with ShurjoPay and returns the checkout link to send the student to. */
    public function startShurjoPay(Application $application, string $ip): string
    {
        $method = $this->assertMethod($application, 'shurjopay');

        if (! ShurjoPayClient::configured()) {
            throw ValidationException::withMessages(['payment' => 'Online payment is not available right now.']);
        }

        $application->loadMissing(['profile', 'addresses', 'user']);
        $payment = $this->newPayment($application, $method, 'shurjopay', Payment::INITIATED);

        try {
            $result = $this->shurjopay->initiate([
                'return_url' => route('student.payments.shurjopay.return'),
                'cancel_url' => route('student.payments.shurjopay.cancel'),
                'amount_minor' => $payment->amount_minor,
                'order_id' => $payment->merchant_ref,
                'customer_name' => $application->profile?->name ?: $application->user->name,
                'customer_address' => (string) ($application->addresses->firstWhere('kind', 'present')?->address ?: 'N/A'),
                'customer_email' => $application->user->email,
                'customer_phone' => (string) ($application->profile?->emergency_phone ?: $application->user->mobile),
                'client_ip' => $ip,
                'tracking_no' => (string) $application->tracking_no,
            ]);
        } catch (PaymentGatewayException $e) {
            $payment->status = Payment::FAILED;
            $payment->failure_reason = Str::limit($e->getMessage(), 150, '');
            $payment->save();
            PaymentEvent::log($payment, 'initiate', 'initiate_error', ['message' => $payment->failure_reason]);

            throw ValidationException::withMessages(['payment' => 'Could not start the payment. Please try again in a moment.']);
        }

        $payment->gateway_ref = $result['sp_order_id'];
        $payment->status = Payment::PENDING;
        $payment->save();
        PaymentEvent::log($payment, 'initiate', 'redirected');

        return $result['checkout_url'];
    }

    /** The student came back from the gateway: ask the gateway what really happened. */
    public function handleReturn(User $user, string $spOrderId, string $source): Payment
    {
        $payment = Payment::where('gateway', 'shurjopay')->where('gateway_ref', $spOrderId)->first();

        abort_unless($payment && $payment->application->user_id === $user->id, 404);

        return $this->verify($payment, $source);
    }

    /** Server-to-server check of a pending gateway payment. Safe to run any number of times. */
    public function verify(Payment $payment, string $source): Payment
    {
        if ($payment->gateway !== 'shurjopay' || ! $payment->gateway_ref || ! in_array($payment->status, [Payment::INITIATED, Payment::PENDING], true)) {
            return $payment;
        }

        try {
            $result = $this->shurjopay->verify($payment->gateway_ref);
        } catch (PaymentGatewayException $e) {
            PaymentEvent::log($payment, $source, 'verify_error', ['message' => Str::limit($e->getMessage(), 150, '')]);

            return $payment;
        }

        return $this->apply($payment, $result, $source);
    }

    private function apply(Payment $payment, GatewayResult $result, string $source): Payment
    {
        return DB::transaction(function () use ($payment, $result, $source) {
            $p = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($p->status === Payment::SUCCESS) {
                PaymentEvent::log($p, $source, 'duplicate_notification', $result->raw);

                return $p;
            }

            if (! in_array($p->status, [Payment::INITIATED, Payment::PENDING], true)) {
                PaymentEvent::log($p, $source, 'ignored_' . $p->status, $result->raw);

                return $p;
            }

            if ($result->status === GatewayResult::SUCCESS) {
                $problems = [];
                if ($result->spOrderId !== '' && $result->spOrderId !== $p->gateway_ref) { $problems[] = 'gateway order id'; }
                if ($result->merchantRef !== $p->merchant_ref) { $problems[] = 'merchant reference'; }
                if ($result->amountMinor !== (int) $p->amount_minor) { $problems[] = 'amount'; }
                if ($result->currency !== config('payments.currency')) { $problems[] = 'currency'; }

                if ($problems) {
                    $p->status = Payment::FAILED;
                    $p->failure_reason = 'Verification mismatch: ' . implode(', ', $problems);
                    $p->save();
                    PaymentEvent::log($p, $source, 'verification_mismatch', $result->raw);
                    AuditLog::record('payment.mismatch', $p, null, ['reason' => $p->failure_reason, 'merchant_ref' => $p->merchant_ref]);

                    return $p;
                }

                $this->markSuccess($p, $result->bankTrxId, $result->channel, $source, $result->raw);

                return $p;
            }

            if ($result->status === GatewayResult::CANCELLED) {
                $p->status = Payment::CANCELLED;
                $p->save();
            } elseif ($result->status === GatewayResult::FAILED) {
                $p->status = Payment::FAILED;
                $p->failure_reason = 'Declined by the gateway';
                $p->save();
            }

            PaymentEvent::log($p, $source, $result->status, $result->raw);

            return $p;
        });
    }

    // ------------------------------------------------------------------ manual payment

    public function submitManual(Application $application, array $data): Payment
    {
        $method = $this->assertMethod($application, 'manual');

        if ($application->payments()->where('status', Payment::MANUAL_PENDING)->exists()) {
            throw ValidationException::withMessages(['payment' => 'You already have a payment waiting for verification.']);
        }

        $taken = Payment::where('gateway', 'manual')->where('manual_trx_id', $data['manual_trx_id'])
            ->whereIn('status', [Payment::MANUAL_PENDING, Payment::SUCCESS])->exists();
        if ($taken) {
            throw ValidationException::withMessages(['manual_trx_id' => 'This transaction id has already been submitted.']);
        }

        return DB::transaction(function () use ($application, $method, $data) {
            $payment = $this->newPayment($application, $method, 'manual', Payment::MANUAL_PENDING);
            $payment->sender_no = $data['sender_no'];
            $payment->manual_trx_id = $data['manual_trx_id'];
            $payment->save();
            PaymentEvent::log($payment, 'manual', 'manual_submitted');

            return $payment;
        });
    }

    public function approveManual(User $actor, Payment $payment): Payment
    {
        return DB::transaction(function () use ($actor, $payment) {
            $p = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($p->status !== Payment::MANUAL_PENDING) {
                throw ValidationException::withMessages(['payment' => 'This payment is not waiting for verification.']);
            }

            $p->verified_by = $actor->id;
            $p->verified_at = now();
            $this->markSuccess($p, $p->manual_trx_id, 'manual', 'manual', ['message' => 'Approved by staff']);

            return $p;
        });
    }

    public function rejectManual(User $actor, Payment $payment, ?string $remark): Payment
    {
        if (trim((string) $remark) === '') {
            throw ValidationException::withMessages(['remark' => 'A reason is required to reject a payment.']);
        }

        return DB::transaction(function () use ($actor, $payment, $remark) {
            $p = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($p->status !== Payment::MANUAL_PENDING) {
                throw ValidationException::withMessages(['payment' => 'This payment is not waiting for verification.']);
            }

            $p->status = Payment::MANUAL_REJECTED;
            $p->reject_reason = $remark;
            $p->verified_by = $actor->id;
            $p->verified_at = now();
            $p->save();

            PaymentEvent::log($p, 'manual', 'manual_rejected', ['reason' => $remark]);
            AuditLog::record('payment.rejected', $p, null, ['reason' => $remark, 'merchant_ref' => $p->merchant_ref]);

            return $p;
        });
    }

    // ------------------------------------------------------------------ no fee

    /** A class with no payable fee needs no payment: the application is settled (and gets its roll) when it is submitted. */
    public function settleFree(Application $application): Payment
    {
        $payment = $this->newPayment($application, null, 'free', Payment::SUCCESS, 0);
        $payment->paid_at = now();
        $payment->success_key = 'app:' . $application->id . ':' . $payment->purpose;
        $payment->save();

        PaymentEvent::log($payment, 'manual', 'free_application');
        $this->rolls->allocate($application);

        return $payment;
    }

    // ------------------------------------------------------------------ internals

    private function markSuccess(Payment $p, ?string $txn, ?string $channel, string $source, array $payload): void
    {
        $p->status = Payment::SUCCESS;
        $p->paid_at = now();
        $p->gateway_txn_id = $txn;
        $p->channel = $channel;
        $p->success_key = 'app:' . $p->application_id . ':' . $p->purpose;

        try {
            $p->save();
        } catch (UniqueConstraintViolationException $e) {
            // The application is already paid: this second payment must be refunded by hand, it is never counted twice.
            $p->success_key = null;
            $p->status = Payment::FAILED;
            $p->failure_reason = 'Duplicate payment: the application was already paid';
            $p->save();
            PaymentEvent::log($p, $source, 'duplicate_success', $payload);
            AuditLog::record('payment.duplicate_success', $p, null, ['merchant_ref' => $p->merchant_ref, 'gateway_ref' => $p->gateway_ref]);

            return;
        }

        $this->rolls->allocate($p->application);

        PaymentEvent::log($p, $source, 'success', $payload);
        AuditLog::record('payment.succeeded', $p, null, ['merchant_ref' => $p->merchant_ref, 'amount_minor' => $p->amount_minor, 'gateway' => $p->gateway]);
    }

    private function assertMethod(Application $application, string $key): PaymentMethod
    {
        if (! $this->canPay($application)) {
            throw ValidationException::withMessages(['payment' => 'No payment is due for this application.']);
        }

        $method = $this->activeMethod();

        if (! $method || $method->key !== $key) {
            throw ValidationException::withMessages(['payment' => 'This payment method is not available right now.']);
        }

        return $method;
    }

    private function newPayment(Application $application, ?PaymentMethod $method, string $gateway, string $status, ?int $amount = null): Payment
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $payment = new Payment();
                $payment->uuid = (string) Str::uuid();
                $payment->application_id = $application->id;
                $payment->payment_method_id = $method?->id;
                $payment->gateway = $gateway;
                $payment->purpose = 'application';
                $payment->merchant_ref = 'ADM' . now()->format('ymd') . strtoupper(Str::random(8));
                $payment->amount_minor = $amount ?? (int) $application->payable_minor;
                $payment->currency = config('payments.currency');
                $payment->status = $status;
                $payment->save();

                return $payment;
            } catch (UniqueConstraintViolationException $e) {
                // the random reference collided: draw another one
            }
        }

        throw ValidationException::withMessages(['payment' => 'Could not create the payment. Please try again.']);
    }
}
