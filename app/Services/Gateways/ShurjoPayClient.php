<?php

namespace App\Services\Gateways;

use App\Exceptions\PaymentGatewayException;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * ShurjoPay REST API (official documentation: shurjopay.com.bd/developers/shurjopay-restapi).
 *   POST {base}/get_token      username + password            -> token, store_id
 *   POST {base}/secret-pay     Bearer token + order details   -> checkout_url, sp_order_id
 *   POST {base}/verification   Bearer token + sp order id     -> payment status (sp_code 1000 = success)
 * There is no signature or webhook: the only trustworthy answer is the verification call made by this server.
 */
class ShurjoPayClient
{
    public static function configured(): bool
    {
        return filled(config('payments.shurjopay.username')) && filled(config('payments.shurjopay.password')) && filled(config('payments.shurjopay.prefix'));
    }

    public function baseUrl(): string
    {
        return config('payments.shurjopay.mode') === 'live'
            ? (string) config('payments.shurjopay.live_url')
            : (string) config('payments.shurjopay.sandbox_url');
    }

    /** @return array{checkout_url: string, sp_order_id: string} */
    public function initiate(array $order): array
    {
        $token = $this->token();

        $response = $this->send('/secret-pay', [
            'prefix' => config('payments.shurjopay.prefix'),
            'token' => $token['token'],
            'store_id' => $token['store_id'],
            'return_url' => $order['return_url'],
            'cancel_url' => $order['cancel_url'],
            'amount' => number_format($order['amount_minor'] / 100, 2, '.', ''),
            'order_id' => $order['order_id'],
            'currency' => config('payments.currency'),
            'customer_name' => $order['customer_name'],
            'customer_address' => $order['customer_address'],
            'customer_email' => $order['customer_email'],
            'customer_phone' => $order['customer_phone'],
            'customer_city' => config('payments.shurjopay.customer_city'),
            'customer_post_code' => config('payments.shurjopay.customer_post_code'),
            'client_ip' => $order['client_ip'],
            'value1' => $order['tracking_no'],
        ]);

        $data = $response->json();
        $checkout = (string) ($data['checkout_url'] ?? '');
        $spOrderId = (string) ($data['sp_order_id'] ?? '');
        $host = strtolower((string) parse_url($checkout, PHP_URL_HOST));

        // never redirect a student to a host that is not ShurjoPay, whatever the response says
        if ($spOrderId === '' || ! str_starts_with($checkout, 'https://') || ! str_ends_with($host, 'shurjopayment.com')) {
            throw new PaymentGatewayException('ShurjoPay did not return a valid checkout link.');
        }

        return ['checkout_url' => $checkout, 'sp_order_id' => $spOrderId];
    }

    public function verify(string $spOrderId): GatewayResult
    {
        $data = $this->send('/verification', ['order_id' => $spOrderId])->json();

        // the API answers with a list holding one record; accept a bare record too
        if (is_array($data) && array_is_list($data)) {
            $data = $data[0] ?? null;
        }

        if (! is_array($data) || ! isset($data['sp_code'])) {
            throw new PaymentGatewayException('ShurjoPay returned an unreadable verification answer.');
        }

        $status = match ((string) $data['sp_code']) {
            '1000' => GatewayResult::SUCCESS,
            '1001' => GatewayResult::FAILED,
            '1002' => GatewayResult::CANCELLED,
            default => GatewayResult::PENDING, // unknown code: not a success, checked again by the reconcile job
        };

        return new GatewayResult(
            $status,
            (string) ($data['order_id'] ?? ''),
            (string) ($data['customer_order_id'] ?? ''),
            Money::toMinor($data['amount'] ?? 0),
            (string) ($data['currency'] ?? ''),
            isset($data['bank_trx_id']) ? (string) $data['bank_trx_id'] : null,
            isset($data['method']) ? (string) $data['method'] : null,
            Arr::only($data, \App\Models\PaymentEvent::KEEP),
        );
    }

    /** @return array{token: string, store_id: string} */
    private function token(): array
    {
        $key = 'shurjopay.token.' . md5(config('payments.shurjopay.mode') . '|' . config('payments.shurjopay.username'));

        return Cache::remember($key, now()->addMinutes(10), function () {
            try {
                $response = Http::acceptJson()->asJson()->timeout((int) config('payments.shurjopay.timeout'))
                    ->post($this->baseUrl() . '/get_token', [
                        'username' => config('payments.shurjopay.username'),
                        'password' => config('payments.shurjopay.password'),
                    ]);
            } catch (ConnectionException $e) {
                throw new PaymentGatewayException('Could not reach ShurjoPay.');
            }

            $data = $response->json();

            if (! $response->successful() || empty($data['token']) || empty($data['store_id'])) {
                throw new PaymentGatewayException('ShurjoPay did not accept the configured credentials.');
            }

            return ['token' => (string) $data['token'], 'store_id' => (string) $data['store_id']];
        });
    }

    /** Authenticated POST; a rejected (expired) token is refreshed once. */
    private function send(string $path, array $payload): Response
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $token = $this->token();

            try {
                $response = Http::acceptJson()->asJson()->timeout((int) config('payments.shurjopay.timeout'))
                    ->withToken($token['token'])->post($this->baseUrl() . $path, $payload);
            } catch (ConnectionException $e) {
                throw new PaymentGatewayException('Could not reach ShurjoPay.');
            }

            if ($response->status() === 401 && $attempt === 0) {
                Cache::forget('shurjopay.token.' . md5(config('payments.shurjopay.mode') . '|' . config('payments.shurjopay.username')));
                continue;
            }

            if (! $response->successful()) {
                throw new PaymentGatewayException('ShurjoPay answered with an error (HTTP ' . $response->status() . ').');
            }

            return $response;
        }

        throw new PaymentGatewayException('ShurjoPay rejected the request.');
    }
}
