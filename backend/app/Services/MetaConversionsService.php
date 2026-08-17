<?php

namespace App\Services;

use App\Models\MetaConversionEvent;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaConversionsService
{
    public function __construct(
        private readonly MetaTrackingSettings $settings,
        private readonly MetaEventIdService $eventIds
    ) {}

    public function purchaseEventId(Order $order): string
    {
        return $this->eventIds->purchase($order);
    }

    public function createPurchaseEvent(Order $order): ?MetaConversionEvent
    {
        $order = $order->fresh(['items']);
        if (! $order || $order->payment_status !== 'paid') {
            return null;
        }

        $trackingContext = $this->trackingContext($order);
        if (! $this->settings->trackingAllowed($trackingContext)) {
            return null;
        }

        $event = MetaConversionEvent::firstOrCreate(
            ['event_name' => 'Purchase', 'event_id' => $this->purchaseEventId($order)],
            ['order_id' => $order->id, 'status' => 'pending']
        );

        if (! $this->settings->capiConfigured()) {
            $event->forceFill([
                'status' => 'failed',
                'last_error_code' => 'missing_config',
                'last_error_message' => 'Meta Pixel ID, CAPI access token, or Graph API version is not configured.',
            ])->save();

            return $event;
        }

        if (! $this->settings->capiEnabled() && $event->status === 'pending') {
            $event->forceFill(['status' => 'skipped'])->save();
        }

        return $event;
    }

    public function sendStoredEvent(MetaConversionEvent $event): void
    {
        if ($event->event_name !== 'Purchase' || $event->sent_at) {
            return;
        }

        if (! $this->settings->capiEnabled()) {
            $event->forceFill(['status' => $this->settings->capiConfigured() ? 'skipped' : 'failed'])->save();

            return;
        }

        $order = Order::with('items', 'paymentTransactions')->find($event->order_id);
        if (! $order || $order->payment_status !== 'paid') {
            $event->forceFill([
                'status' => 'failed',
                'last_error_code' => 'order_not_paid',
                'last_error_message' => 'Order is missing or is not paid.',
            ])->save();

            return;
        }

        $event->increment('attempts');
        $payload = $this->purchasePayload($order, $event->event_id);
        $requestBody = $payload;
        $testEventCode = $this->settings->testEventCode();
        if ($testEventCode !== '') {
            $requestBody['test_event_code'] = $testEventCode;
        }
        $requestBody['access_token'] = $this->settings->capiToken();

        try {
            $response = Http::asJson()
                ->timeout((int) config('services.meta.capi_timeout_seconds', 5))
                ->post($this->endpoint(), $requestBody);
        } catch (ConnectionException $exception) {
            $this->markTransientFailure($event, 'network_error', $exception->getMessage());
            throw $exception;
        }

        if ($response->successful()) {
            $event->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'last_error_code' => null,
                'last_error_message' => null,
            ])->save();

            return;
        }

        $error = $response->json('error') ?: [];
        $code = (string) ($error['code'] ?? $response->status());
        $message = $this->sanitizeError((string) ($error['message'] ?? 'Meta CAPI request failed.'));

        $event->forceFill([
            'status' => 'failed',
            'last_error_code' => $code,
            'last_error_message' => $message,
        ])->save();

        Log::warning('Meta CAPI request failed.', [
            'event_name' => $event->event_name,
            'event_id' => $event->event_id,
            'order_id' => $event->order_id,
            'http_status' => $response->status(),
            'meta_error_code' => $code,
            'message' => $message,
            'attempt' => $event->attempts,
        ]);

        if ($response->serverError() || $response->status() === 429) {
            throw new \RuntimeException('Meta CAPI transient failure: '.$response->status());
        }
    }

    public function sendTestEvent(): array
    {
        if (! $this->settings->capiEnabled()) {
            return ['ok' => false, 'message' => 'Meta CAPI is not fully configured or enabled.'];
        }

        $body = [
            'data' => [[
                'event_name' => 'PageView',
                'event_time' => now()->timestamp,
                'event_id' => 'test:'.now()->timestamp,
                'action_source' => 'website',
                'user_data' => ['client_user_agent' => 'Learn by Bluxor admin test'],
            ]],
            'access_token' => $this->settings->capiToken(),
        ];

        if ($this->settings->testEventCode() !== '') {
            $body['test_event_code'] = $this->settings->testEventCode();
        }

        $response = Http::asJson()->timeout((int) config('services.meta.capi_timeout_seconds', 5))->post($this->endpoint(), $body);

        if ($response->successful()) {
            return ['ok' => true, 'message' => 'Meta accepted the test event.'];
        }

        return [
            'ok' => false,
            'message' => 'Meta rejected the test event: '.$this->sanitizeError((string) ($response->json('error.message') ?: 'request failed.')),
        ];
    }

    public function purchasePayload(Order $order, string $eventId): array
    {
        $order->loadMissing('items', 'paymentTransactions');
        $trackingContext = $this->trackingContext($order);
        $userData = $this->userData($order, $trackingContext);
        $eventSourceUrl = $trackingContext['event_source_url'] ?? $trackingContext['landing_page_url'] ?? null;

        $event = [
            'event_name' => 'Purchase',
            'event_time' => $this->purchaseTimestamp($order),
            'event_id' => $eventId,
            'action_source' => 'website',
            'user_data' => $userData,
            'custom_data' => [
                'currency' => strtoupper($order->currency),
                'value' => $this->minorToDecimal($order->total_minor, $order->currency),
                'content_ids' => $this->contentIds($order),
                'content_type' => 'product',
                'num_items' => max(1, (int) $order->items->sum('quantity')),
                'order_id' => $order->order_number,
            ],
        ];

        if (is_string($eventSourceUrl) && $eventSourceUrl !== '') {
            $event['event_source_url'] = $eventSourceUrl;
        }

        return ['data' => [$event]];
    }

    public function hashEmail(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    public function hashPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return $digits ? hash('sha256', $digits) : null;
    }

    public function minorToDecimal(int $amountMinor, string $currency): float
    {
        return round($amountMinor / $this->minorFactor($currency), 2);
    }

    public function contentIds(Order $order): array
    {
        return $order->items->map(function ($item) {
            if ($item->bundle_id) {
                return 'bundle:'.$item->bundle_id;
            }

            return 'product:'.$item->product_id;
        })->filter()->values()->all();
    }

    private function userData(Order $order, array $trackingContext): array
    {
        $data = [
            'em' => [$this->hashEmail($order->customer_email)],
        ];

        if ($phone = $this->hashPhone($order->customer_phone)) {
            $data['ph'] = [$phone];
        }

        foreach (['client_ip_address', 'client_user_agent', 'fbp', 'fbc'] as $key) {
            if (isset($trackingContext[$key]) && is_string($trackingContext[$key]) && trim($trackingContext[$key]) !== '') {
                $data[$key] = trim($trackingContext[$key]);
            }
        }

        return $data;
    }

    private function trackingContext(Order $order): array
    {
        $metadata = $order->metadata ?: [];

        return is_array($metadata['tracking_context'] ?? null) ? $metadata['tracking_context'] : [];
    }

    private function purchaseTimestamp(Order $order): int
    {
        $paidAt = $order->paymentTransactions
            ->filter(fn (PaymentTransaction $transaction) => $transaction->status === 'paid' && $transaction->paid_at)
            ->sortByDesc('paid_at')
            ->first()?->paid_at;

        return ($paidAt ?: $order->updated_at ?: now())->timestamp;
    }

    private function endpoint(): string
    {
        return 'https://graph.facebook.com/'.$this->settings->graphApiVersion().'/'.$this->settings->pixelId().'/events';
    }

    private function markTransientFailure(MetaConversionEvent $event, string $code, string $message): void
    {
        $event->forceFill([
            'status' => 'failed',
            'last_error_code' => $code,
            'last_error_message' => $this->sanitizeError($message),
        ])->save();
    }

    private function sanitizeError(string $message): string
    {
        return str($message)->replace($this->settings->capiToken(), '[redacted]')->limit(500)->toString();
    }

    private function minorFactor(string $currency): int
    {
        return match (strtoupper($currency)) {
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' => 1,
            default => 100,
        };
    }
}
