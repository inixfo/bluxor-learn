<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PipraPayGateway implements PaymentGateway
{
    public function initiate(Order $order): array
    {
        if (! config('services.piprapay.enabled', true)) {
            throw ValidationException::withMessages(['gateway' => ['PipraPay is disabled.']]);
        }

        $apiKey = $this->apiKey();
        $attemptUuid = (string) Str::uuid();
        $metadata = [
            'order_uuid' => $order->uuid,
            'order_number' => $order->order_number,
            'payment_attempt_uuid' => $attemptUuid,
        ];

        $payload = [
            'full_name' => $order->customer_name ?: 'Learn by Bluxor Customer',
            'email_mobile' => $order->customer_email ?: $order->customer_phone,
            'amount' => $this->majorAmount($order->total_minor),
            'metadata' => $metadata,
            'redirect_url' => $this->returnUrl($order),
            'return_type' => 'GET',
            'cancel_url' => $this->cancelUrl($order),
            'webhook_url' => $this->webhookUrl(),
            'currency' => strtoupper($order->currency ?: config('services.piprapay.currency', 'BDT')),
        ];

        $transaction = PaymentTransaction::updateOrCreate(
            ['order_id' => $order->id, 'gateway' => $this->name()],
            [
                'uuid' => $attemptUuid,
                'provider_transaction_id' => null,
                'provider_reference' => $order->order_number,
                'amount_minor' => $order->total_minor,
                'currency' => $order->currency,
                'status' => 'initiated',
                'normalized_state' => 'initiated',
                'initiated_at' => now(),
                'raw_response' => ['request_metadata' => $metadata],
            ]
        );

        $response = Http::timeout(20)
            ->acceptJson()
            ->asJson()
            ->withHeaders($this->headers($apiKey))
            ->post($this->endpoint('/api/create-charge'), $payload);

        if (! $response->ok()) {
            $transaction->forceFill([
                'status' => 'failed',
                'normalized_state' => 'create_failed',
                'failed_at' => now(),
                'raw_response' => $this->safePayload($response->json() ?: ['body' => $response->body()]),
            ])->save();

            throw ValidationException::withMessages(['gateway' => ['PipraPay Create Charge request failed.']]);
        }

        $provider = $response->json();
        if (! is_array($provider)) {
            throw ValidationException::withMessages(['gateway' => ['PipraPay Create Charge response was invalid.']]);
        }

        $normalized = $this->normalizeCreateResponse($provider);
        if (! $normalized['checkout_url']) {
            throw ValidationException::withMessages(['gateway' => ['PipraPay response did not include a checkout URL.']]);
        }

        $transaction->forceFill([
            'provider_transaction_id' => $normalized['pp_id'],
            'provider_reference' => $normalized['invoice_id'] ?: $order->order_number,
            'status' => 'pending',
            'normalized_state' => 'pending',
            'raw_response' => $this->safePayload($provider),
        ])->save();

        return [
            'gateway' => $this->name(),
            'order_number' => $order->order_number,
            'payment_attempt_uuid' => $attemptUuid,
            'provider_payment_id' => $normalized['pp_id'],
            'invoice_id' => $normalized['invoice_id'],
            'checkout_url' => $normalized['checkout_url'],
            'redirect_url' => $normalized['checkout_url'],
            'currency' => $order->currency,
            'amount_minor' => $order->total_minor,
        ];
    }

    public function verify(string $providerPaymentId): array
    {
        if ($providerPaymentId === '') {
            throw ValidationException::withMessages(['pp_id' => ['PipraPay payment ID is required.']]);
        }

        $response = Http::timeout(20)
            ->acceptJson()
            ->asJson()
            ->withHeaders($this->headers($this->apiKey()))
            ->post($this->endpoint('/api/verify-payment'), ['pp_id' => $providerPaymentId]);

        if (! $response->ok()) {
            throw ValidationException::withMessages(['gateway' => ['PipraPay verification request failed.']]);
        }

        $provider = $response->json();
        if (! is_array($provider)) {
            throw ValidationException::withMessages(['gateway' => ['PipraPay verification response was invalid.']]);
        }

        return $this->dataPayload($provider);
    }

    public function normalizeVerified(Order $order, array $provider): array
    {
        $provider = $this->dataPayload($provider);
        $status = strtolower((string) ($provider['status'] ?? ''));
        $metadata = $this->metadata($provider);
        $ppId = (string) ($provider['pp_id'] ?? $provider['transaction_id'] ?? '');
        $transactionId = (string) ($provider['transaction_id'] ?? '');
        $amountMinor = $this->minor($provider['amount'] ?? $provider['total'] ?? null);
        $currency = strtoupper((string) ($provider['currency'] ?? ''));

        if ($ppId === '') {
            throw ValidationException::withMessages(['pp_id' => ['PipraPay verification response did not include pp_id.']]);
        }

        if (! in_array($status, ['completed', 'success', 'paid'], true)) {
            throw ValidationException::withMessages(['status' => ['PipraPay payment is not completed.']]);
        }

        $this->assertMatchesOrder($order, $metadata, $amountMinor, $currency);

        return [
            'state' => $status,
            'valid' => true,
            'order_number' => $order->order_number,
            'provider_transaction_id' => $ppId,
            'validation_id' => $transactionId ?: $ppId,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'payment_method' => $provider['payment_method'] ?? $provider['gateway'] ?? null,
            'metadata' => $metadata,
            'raw' => $this->safePayload($provider),
        ];
    }

    public function normalizeFailedPayload(Order $order, array $payload, string $state): array
    {
        $payload = $this->dataPayload($payload);

        return [
            'state' => $state,
            'valid' => false,
            'order_number' => $order->order_number,
            'provider_transaction_id' => $payload['pp_id'] ?? $payload['transaction_id'] ?? null,
            'validation_id' => $payload['transaction_id'] ?? null,
            'amount_minor' => $this->minor($payload['amount'] ?? $payload['total'] ?? $order->total_minor / 100),
            'currency' => strtoupper((string) ($payload['currency'] ?? $order->currency)),
            'metadata' => $this->metadata($payload),
            'raw' => $this->safePayload($payload),
        ];
    }

    public function validateWebhook(array $payload, ?string $receivedApiKey): array
    {
        $expected = (string) config('services.piprapay.api_key');
        if ($expected === '' || ! hash_equals($expected, (string) $receivedApiKey)) {
            throw ValidationException::withMessages(['gateway' => ['Unauthorized PipraPay webhook.']]);
        }

        $payload = $this->dataPayload($payload);
        if (empty($payload['pp_id'])) {
            throw ValidationException::withMessages(['pp_id' => ['PipraPay webhook missing pp_id.']]);
        }

        return $payload;
    }

    public function refund(Order $order, string $providerPaymentId): array
    {
        if ($providerPaymentId === '') {
            throw ValidationException::withMessages(['pp_id' => ['Refund requires a PipraPay payment ID.']]);
        }

        $response = Http::timeout(20)
            ->acceptJson()
            ->asJson()
            ->withHeaders($this->headers($this->apiKey()))
            ->post($this->endpoint('/api/refund-payment'), ['pp_id' => $providerPaymentId]);

        if (! $response->ok()) {
            throw ValidationException::withMessages(['gateway' => ['PipraPay refund request failed.']]);
        }

        $provider = $response->json();
        if (! is_array($provider)) {
            throw ValidationException::withMessages(['gateway' => ['PipraPay refund response was invalid.']]);
        }

        $payload = $this->dataPayload($provider);
        $status = strtolower((string) ($payload['status'] ?? $payload['refund_status'] ?? ''));
        $successFlag = $payload['success'] ?? $payload['status'] ?? $provider['status'] ?? null;
        $success = in_array($status, ['completed', 'success', 'refunded', 'processed'], true)
            || $successFlag === true
            || strtolower((string) $successFlag) === 'true';

        if (! $success) {
            throw ValidationException::withMessages(['gateway' => ['PipraPay refund was not confirmed successful.']]);
        }

        return [
            'provider_refund_id' => (string) ($payload['refund_id'] ?? $payload['transaction_id'] ?? $providerPaymentId),
            'provider_payment_id' => $providerPaymentId,
            'status' => $status ?: 'refunded',
            'amount_minor' => $this->minor($payload['refund_amount'] ?? $payload['amount'] ?? $order->total_minor / 100),
            'currency' => strtoupper((string) ($payload['currency'] ?? $order->currency)),
            'raw' => $this->safePayload($payload),
        ];
    }

    public function name(): string
    {
        return 'piprapay';
    }

    public function metadata(array $payload): array
    {
        $metadata = $payload['metadata'] ?? [];
        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        return is_array($metadata) ? $metadata : [];
    }

    private function assertMatchesOrder(Order $order, array $metadata, int $amountMinor, string $currency): void
    {
        if (($metadata['order_uuid'] ?? null) !== $order->uuid || ($metadata['order_number'] ?? null) !== $order->order_number) {
            throw ValidationException::withMessages(['metadata' => ['PipraPay metadata does not match this order.']]);
        }

        if ($amountMinor !== (int) $order->total_minor) {
            throw ValidationException::withMessages(['amount' => ['Payment amount does not match the server-side order total.']]);
        }

        if ($currency !== strtoupper($order->currency)) {
            throw ValidationException::withMessages(['currency' => ['Payment currency does not match the order currency.']]);
        }
    }

    private function normalizeCreateResponse(array $provider): array
    {
        $data = $this->dataPayload($provider);

        return [
            'pp_id' => $data['pp_id'] ?? $data['transaction_id'] ?? $data['payment_id'] ?? null,
            'invoice_id' => $data['invoice_id'] ?? $data['invoiceId'] ?? null,
            'checkout_url' => $data['checkout_url'] ?? $data['payment_url'] ?? $data['redirect_url'] ?? $data['url'] ?? null,
        ];
    }

    private function dataPayload(array $provider): array
    {
        foreach (['data', 'payment', 'transaction', 'response'] as $key) {
            if (isset($provider[$key]) && is_array($provider[$key])) {
                return $provider[$key] + $provider;
            }
        }

        return $provider;
    }

    private function headers(string $apiKey): array
    {
        return [
            'mh-piprapay-api-key' => $apiKey,
            'MHS-PIPRAPAY-API-KEY' => $apiKey,
        ];
    }

    private function apiKey(): string
    {
        $apiKey = (string) config('services.piprapay.api_key');
        if ($apiKey === '') {
            throw ValidationException::withMessages(['gateway' => ['PipraPay API key is not configured.']]);
        }

        return $apiKey;
    }

    private function endpoint(string $path): string
    {
        $baseUrl = rtrim((string) config('services.piprapay.base_url'), '/');
        if ($baseUrl === '') {
            throw ValidationException::withMessages(['gateway' => ['PipraPay base URL is not configured.']]);
        }

        return $baseUrl.$path;
    }

    private function returnUrl(Order $order): string
    {
        $base = rtrim((string) config('services.piprapay.return_url'), '/');
        $url = $base !== '' ? $base : rtrim((string) config('app.frontend_url', env('FRONTEND_URL', url('/'))), '/').'/checkout/success';

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query(['order' => $order->order_number]);
    }

    private function cancelUrl(Order $order): string
    {
        $base = rtrim((string) config('services.piprapay.cancel_url'), '/');
        $url = $base !== '' ? $base : rtrim((string) config('app.frontend_url', env('FRONTEND_URL', url('/'))), '/').'/checkout';

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query(['order' => $order->order_number, 'cancelled' => 1]);
    }

    private function webhookUrl(): string
    {
        $configured = (string) config('services.piprapay.webhook_url');

        return $configured !== '' ? $configured : url('/api/v1/payments/piprapay/webhook');
    }

    private function majorAmount(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }

    private function minor(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function safePayload(array $payload): array
    {
        unset($payload['api_key'], $payload['key'], $payload['secret'], $payload['token']);

        return $payload;
    }
}
