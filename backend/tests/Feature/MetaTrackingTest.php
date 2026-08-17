<?php

namespace Tests\Feature;

use App\Jobs\SendMetaConversionEvent;
use App\Models\MetaConversionEvent;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\MetaConversionsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MetaTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_capi_skips_delivery_without_meta_http(): void
    {
        $this->enableMeta(capiEnabled: false);
        Queue::fake();

        $order = $this->paidGuestOrder();

        $event = MetaConversionEvent::firstOrFail();
        $this->assertSame('skipped', $event->status);
        Queue::assertNotPushed(SendMetaConversionEvent::class);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_missing_capi_config_fails_safely_without_undoing_purchase(): void
    {
        $this->enableMeta(pixelId: '', token: '', capiEnabled: true);
        Queue::fake();

        $order = $this->paidGuestOrder();

        $event = MetaConversionEvent::firstOrFail();
        $this->assertSame('failed', $event->status);
        $this->assertSame('missing_config', $event->last_error_code);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_paid_order_creates_one_deterministic_purchase_event_for_repeated_callbacks(): void
    {
        $this->enableMeta();
        Queue::fake();
        $this->seed(DatabaseSeeder::class);
        $this->fakePipraPay();

        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();
        $orderResponse = $this->postJson('/api/v1/checkout/orders', [
            'product_id' => $product->id,
            'customer_name' => 'Guest Buyer',
            'customer_email' => 'buyer@example.com',
            'customer_phone' => '+8801711111111',
            'payment_method' => 'piprapay',
        ])->assertCreated()->json('data');

        $order = Order::where('order_number', $orderResponse['order']['order_number'])->firstOrFail();
        $this->fakePipraPayVerify($order, 'pp-meta-repeat', 'completed');

        $this->postJson('/api/v1/payments/piprapay/webhook', ['pp_id' => 'pp-meta-repeat'], $this->webhookHeaders())->assertOk();
        $this->postJson('/api/v1/payments/piprapay/webhook', ['pp_id' => 'pp-meta-repeat'], $this->webhookHeaders())->assertOk();

        $this->assertSame(1, MetaConversionEvent::where('event_name', 'Purchase')->where('event_id', 'purchase:'.$order->order_number)->count());
        Queue::assertPushed(SendMetaConversionEvent::class, 1);
    }

    public function test_pending_failed_and_cancelled_orders_do_not_create_purchase_events(): void
    {
        $this->enableMeta();
        Queue::fake();
        $this->seed(DatabaseSeeder::class);
        $this->fakePipraPay();

        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();
        $pendingResponse = $this->postJson('/api/v1/checkout/orders', [
            'product_id' => $product->id,
            'customer_name' => 'Pending Buyer',
            'customer_email' => 'pending@example.com',
            'customer_phone' => '+8801711111111',
            'payment_method' => 'piprapay',
        ])->assertCreated()->json('data');
        $this->assertDatabaseCount('meta_conversion_events', 0);

        $this->postJson('/api/v1/payments/piprapay/cancel', [
            'order' => $pendingResponse['order']['order_number'],
        ])->assertOk();

        $this->assertDatabaseCount('meta_conversion_events', 0);
        Queue::assertNotPushed(SendMetaConversionEvent::class);
    }

    public function test_successful_capi_request_marks_event_sent_and_payload_contains_expected_purchase_data(): void
    {
        $this->enableMeta();
        Queue::fake();
        $order = $this->paidGuestOrder([
            'fbp' => 'fb.1.123.abc',
            'fbc' => 'fb.1.456.def',
            'event_source_url' => 'https://learn.bluxor.com/checkout',
        ]);
        $event = MetaConversionEvent::firstOrFail();

        Http::fake([
            'graph.facebook.com/*' => Http::response(['events_received' => 1], 200),
        ]);

        app(MetaConversionsService::class)->sendStoredEvent($event);

        $event->refresh();
        $this->assertSame('sent', $event->status);
        $this->assertNotNull($event->sent_at);

        Http::assertSent(function ($request) use ($order) {
            $body = $request->data();
            $event = $body['data'][0];

            return str_contains($request->url(), '/v25.0/123456789/events')
                && $body['access_token'] === 'test-token'
                && $event['event_name'] === 'Purchase'
                && $event['event_id'] === 'purchase:'.$order->order_number
                && $event['action_source'] === 'website'
                && $event['event_source_url'] === 'https://learn.bluxor.com/checkout'
                && $event['custom_data']['value'] === app(MetaConversionsService::class)->minorToDecimal($order->total_minor, $order->currency)
                && $event['custom_data']['currency'] === $order->currency
                && $event['custom_data']['content_ids'] === ['product:1']
                && $event['user_data']['fbp'] === 'fb.1.123.abc'
                && $event['user_data']['fbc'] === 'fb.1.456.def'
                && isset($event['user_data']['client_ip_address'])
                && isset($event['user_data']['client_user_agent']);
        });
    }

    public function test_transient_and_permanent_capi_failures_are_isolated_from_paid_order(): void
    {
        $this->enableMeta();
        Queue::fake();
        $order = $this->paidGuestOrder();
        $event = MetaConversionEvent::firstOrFail();

        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 1, 'message' => 'temporary']], 500)]);
        $this->expectException(\RuntimeException::class);

        try {
            app(MetaConversionsService::class)->sendStoredEvent($event);
        } finally {
            $this->assertSame('paid', $order->fresh()->payment_status);
            $this->assertSame(1, $event->fresh()->attempts);
            $this->assertSame('failed', $event->fresh()->status);
        }
    }

    public function test_auth_capi_failure_is_handled_without_retry_exception_or_secret_logging(): void
    {
        $this->enableMeta(token: 'super-secret-token');
        Queue::fake();
        $order = $this->paidGuestOrder();
        $event = MetaConversionEvent::firstOrFail();

        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 190, 'message' => 'bad token super-secret-token']], 401)]);

        app(MetaConversionsService::class)->sendStoredEvent($event);

        $event->refresh();
        $this->assertSame('failed', $event->status);
        $this->assertSame('190', $event->last_error_code);
        $this->assertStringNotContainsString('super-secret-token', $event->last_error_message);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_registered_customer_bundle_purchase_and_public_config_are_supported_without_exposing_token(): void
    {
        $this->enableMeta();
        Queue::fake();
        $this->seed(DatabaseSeeder::class);
        $this->fakePipraPay();

        $customer = User::where('email', 'rakib@example.com')->firstOrFail();
        $bundle = \App\Models\Bundle::where('slug', 'complete-learning-bundle')->firstOrFail();
        $orderResponse = $this->actingAs($customer)->postJson('/api/v1/checkout/orders', [
            'bundle_id' => $bundle->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => '+8801711111111',
            'payment_method' => 'piprapay',
        ])->assertCreated()->json('data');

        $order = Order::where('order_number', $orderResponse['order']['order_number'])->firstOrFail();
        $this->fakePipraPayVerify($order, 'pp-meta-bundle', 'completed');
        $this->postJson('/api/v1/payments/piprapay/webhook', ['pp_id' => 'pp-meta-bundle'], $this->webhookHeaders())->assertOk();

        $payload = app(MetaConversionsService::class)->purchasePayload($order->fresh('items'), 'purchase:'.$order->order_number);
        $this->assertSame(['bundle:'.$bundle->id], $payload['data'][0]['custom_data']['content_ids']);

        $this->getJson('/api/v1/tracking/config')
            ->assertOk()
            ->assertJsonPath('data.meta.enabled', true)
            ->assertJsonMissing(['access_token' => 'test-token']);

        $this->actingAs(User::where('email', 'admin@learn.bluxor.test')->firstOrFail())
            ->getJson('/api/v1/admin/tracking/meta')
            ->assertOk()
            ->assertJsonPath('data.meta.capi_token_configured', true)
            ->assertJsonMissing(['capi_access_token' => 'test-token']);
    }

    public function test_hashing_and_test_event_code_behavior(): void
    {
        $this->enableMeta(testEventCode: 'TEST123');
        $service = app(MetaConversionsService::class);

        $this->assertSame(hash('sha256', 'buyer@example.com'), $service->hashEmail(' Buyer@Example.COM '));
        $this->assertSame(hash('sha256', '8801711111111'), $service->hashPhone('+880 171-111-1111'));

        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);
        $result = $service->sendTestEvent();

        $this->assertTrue($result['ok']);
        Http::assertSent(fn ($request) => $request->data()['test_event_code'] === 'TEST123' && $request->data()['access_token'] === 'test-token');
    }

    private function enableMeta(string $pixelId = '123456789', string $token = 'test-token', bool $capiEnabled = true, string $testEventCode = ''): void
    {
        config([
            'services.meta.pixel_enabled' => true,
            'services.meta.pixel_id' => null,
            'services.meta.capi_enabled' => $capiEnabled,
            'services.meta.capi_access_token' => $token,
            'services.meta.graph_api_version' => 'v25.0',
            'services.meta.capi_test_event_code' => $testEventCode,
            'services.meta.allow_local_pixel' => true,
        ]);

        foreach ([
            'pixel_enabled' => true,
            'pixel_id' => $pixelId,
            'capi_enabled' => $capiEnabled,
            'graph_api_version' => 'v25.0',
        ] as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'tracking', 'key' => $key],
                ['value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function paidGuestOrder(array $trackingContext = []): Order
    {
        if (! Product::exists()) {
            $this->seed(DatabaseSeeder::class);
        }

        $this->fakePipraPay();
        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();

        $orderResponse = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->withHeader('User-Agent', 'Meta test browser')
            ->postJson('/api/v1/checkout/orders', [
                'product_id' => $product->id,
                'customer_name' => 'Guest Buyer',
                'customer_email' => 'buyer@example.com',
                'customer_phone' => '+8801711111111',
                'payment_method' => 'piprapay',
                'tracking_context' => $trackingContext,
            ])->assertCreated()->json('data');

        $order = Order::where('order_number', $orderResponse['order']['order_number'])->firstOrFail();
        $this->fakePipraPayVerify($order, 'pp-'.$order->id, 'completed');
        $this->postJson('/api/v1/payments/piprapay/webhook', ['pp_id' => 'pp-'.$order->id], $this->webhookHeaders())->assertOk();

        return $order->fresh(['items', 'paymentTransactions']);
    }

    private function fakePipraPay(): void
    {
        config([
            'services.piprapay.base_url' => 'https://pipra.test',
            'services.piprapay.api_key' => 'test-key',
        ]);
    }

    private function fakePipraPayVerify(Order $order, string $ppId, string $status): void
    {
        Http::fake([
            'pipra.test/api/verify-payment' => Http::response([
                'status' => $status,
                'pp_id' => $ppId,
                'transaction_id' => 'txn-'.$ppId,
                'amount' => number_format($order->total_minor / 100, 2, '.', ''),
                'currency' => $order->currency,
                'metadata' => [
                    'order_id' => $order->uuid,
                    'order_uuid' => $order->uuid,
                    'order_number' => $order->order_number,
                    'payment_attempt_uuid' => fake()->uuid(),
                ],
            ]),
        ]);
    }

    private function webhookHeaders(): array
    {
        return ['MHS-PIPRAPAY-API-KEY' => 'test-key'];
    }
}
