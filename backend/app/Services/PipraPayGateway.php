<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        if (blank($order->customer_phone)) {
            throw ValidationException::withMessages(['customer_phone' => ['Mobile number is required for PipraPay checkout.']]);
        }

        $attemptUuid = (string) Str::uuid();
        $metadata = [
            'order_id' => $order->uuid,
            'order_uuid' => $order->uuid,
            'order_number' => $order->order_number,
            'payment_attempt_uuid' => $attemptUuid,
        ];

        $payload = [
            'full_name' => $order->customer_name,
            'email_address' => $order->customer_email,
            'mobile_number' => $order->customer_phone,
            'amount' => $this->majorAmount($order->total_minor),
            'currency' => strtoupper($order->currency ?: config('services.piprapay.currency', 'BDT')),
            'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES),
            'return_url' => $this->returnUrl(),
            'webhook_url' => $this->webhookUrl(),
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
            ->post($this->endpoint('/api/checkout/redirect'), $payload);

        if (! $response->ok()) {
            $this->logProviderFailure('checkout_redirect', $response->status(), $response->json(), [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            $transaction->forceFill([
                'status' => 'failed',
                'normalized_state' => 'initiate_failed',
                'failed_at' => now(),
                'raw_response' => $this->safePayload($response->json() ?: ['body' => $response->body()]),
            ])->save();

            throw ValidationException::withMessages(['gateway' => ['Payment gateway could not start the checkout. Please try again.']]);
        }

        $provider = $response->json();
        if (! is_array($provider)) {
            throw ValidationException::withMessages(['gateway' => ['Payment gateway returned an invalid checkout response.']]);
        }

        $normalized = $this->normalizeCreateResponse($provider);
        if (! $normalized['pp_id'] || ! $normalized['checkout_url']) {
            throw ValidationException::withMessages(['gateway' => ['Payment gateway response did not include a checkout URL.']]);
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
            'pp_url' => $normalized['checkout_url'],
            'checkout_url' => $normalized['checkout_url'],
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
            $this->logProviderFailure('verify_payment', $response->status(), $response->json(), ['pp_id' => $providerPaymentId]);

            throw ValidationException::withMessages(['gateway' => ['Payment gateway could not verify the payment. Please try again.']]);
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
        $amountMinor = $this->verifiedAmountMinor($provider);
        $currency = strtoupper((string) ($provider['currency'] ?? ''));

        if ($ppId === '') {
            throw ValidationException::withMessages(['pp_id' => ['PipraPay verification response did not include pp_id.']]);
        }

        if (! PipraPayStatus::isCompleted($status)) {
            throw ValidationException::withMessages(['status' => ['PipraPay payment is not completed.']]);
        }

        $this->assertMatchesOrder($order, $metadata, $amountMinor, $currency);

        return [
            'state' => PipraPayStatus::normalize($status),
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

    public function assertPaymentIdMatches(array $provider, string $expectedProviderPaymentId): void
    {
        $provider = $this->dataPayload($provider);
        $actual = (string) ($provider['pp_id'] ?? $provider['transaction_id'] ?? '');

        if ($expectedProviderPaymentId === '' || $actual === '' || ! hash_equals($expectedProviderPaymentId, $actual)) {
            throw ValidationException::withMessages(['pp_id' => ['PipraPay verification response did not match the expected payment ID.']]);
        }
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

    public function validateWebhook(array $payload, ?string $receivedApiKey = null): array
    {
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
            $this->logProviderFailure('refund_payment', $response->status(), $response->json(), [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'pp_id' => $providerPaymentId,
            ]);

            throw ValidationException::withMessages(['gateway' => ['Payment gateway could not confirm the refund. Please try again.']]);
        }

        $provider = $response->json();
        if (! is_array($provider)) {
            throw ValidationException::withMessages(['gateway' => ['PipraPay refund response was invalid.']]);
        }

        $payload = $this->dataPayload($provider);
        $status = strtolower((string) ($payload['status'] ?? $payload['refund_status'] ?? ''));
        $successFlag = $payload['success'] ?? $payload['status'] ?? $provider['status'] ?? null;

        if (! PipraPayStatus::isRefundSuccess($status, $successFlag)) {
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
        $metadataOrderId = $metadata['order_id'] ?? $metadata['order_uuid'] ?? null;
        if ($metadataOrderId !== $order->uuid || ($metadata['order_number'] ?? null) !== $order->order_number) {
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
            'checkout_url' => $data['pp_url'] ?? $data['checkout_url'] ?? $data['payment_url'] ?? $data['url'] ?? null,
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

    private function returnUrl(): string
    {
        $base = rtrim((string) config('services.piprapay.return_url'), '/');

        return $base !== '' ? $base : url('/api/v1/payments/piprapay/success');
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

    private function verifiedAmountMinor(array $provider): int
    {
        // PipraPay V3 verification returns both `amount` and fee/net totals.
        // `amount` is the merchant checkout amount Learn sent when creating the redirect.
        return $this->minor($provider['amount'] ?? $provider['total'] ?? null);
    }

    private function logProviderFailure(string $endpoint, int $status, mixed $body, array $context = []): void
    {
        $payload = is_array($body) ? $this->safePayload($body) : [];
        Log::warning('PipraPay provider request failed.', array_merge($context, [
            'endpoint' => $endpoint,
            'http_status' => $status,
            'provider_error_code' => $payload['code'] ?? $payload['error_code'] ?? null,
            'provider_error_message' => $payload['message'] ?? $payload['error'] ?? null,
        ]));
    }

    private function safePayload(array $payload): array
    {
        unset($payload['api_key'], $payload['key'], $payload['secret'], $payload['token']);

        return $payload;
    }
}
