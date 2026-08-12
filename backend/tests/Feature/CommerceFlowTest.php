<?php

namespace Tests\Feature;

use App\Jobs\SendPurchaseConfirmationEmail;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\PaymentEvent;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\RefundAttempt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CommerceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_checkout_uses_server_side_price_and_creates_an_order(): void
    {
        $this->seed(DatabaseSeeder::class);
        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();

        $response = $this->postJson('/api/v1/checkout/orders', [
            'product_id' => $product->id,
            'coupon_code' => 'LAUNCH20',
            'customer_name' => 'Guest Buyer',
            'customer_email' => 'guest@example.com',
            'customer_phone' => '01700000000',
            'payment_method' => 'bkash',
            'total_minor' => 1,
        ]);

        $response->assertCreated();

        $order = Order::where('customer_email', 'guest@example.com')->firstOrFail();
        $this->assertSame(99000, $order->subtotal_minor);
        $this->assertSame(19800, $order->discount_minor);
        $this->assertSame(79200, $order->total_minor);
        $this->assertNull($order->user_id);
    }

    public function test_piprapay_create_charge_success_persists_provider_identifier(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->fakePipraPay();
        $order = $this->pendingOrder();

        Http::fake([
            'pipra.test/api/create-charge' => Http::response([
                'pp_id' => 'pp-create-1',
                'checkout_url' => 'https://pay.pipra.test/checkout/pp-create-1',
                'invoice_id' => 'invoice-1',
            ]),
        ]);

        $this->postJson('/api/v1/payments/piprapay/initiate', ['order_number' => $order->order_number])
            ->assertOk()
            ->assertJsonPath('data.gateway', 'piprapay')
            ->assertJsonPath('data.checkout_url', 'https://pay.pipra.test/checkout/pp-create-1');

        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'gateway' => 'piprapay',
            'provider_transaction_id' => 'pp-create-1',
            'status' => 'pending',
        ]);
    }

    public function test_piprapay_create_charge_failure_marks_attempt_failed(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->fakePipraPay();
        $order = $this->pendingOrder();

        Http::fake(['pipra.test/api/create-charge' => Http::response(['message' => 'bad'], 500)]);

        $this->postJson('/api/v1/payments/piprapay/initiate', ['order_number' => $order->order_number])
            ->assertStatus(422);

        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'gateway' => 'piprapay',
            'status' => 'failed',
        ]);
    }

    public function test_duplicate_webhook_creates_entitlements_email_and_payment_event_once(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        $this->fakePipraPay();
        $order = $this->pendingOrder('practical-bug-bounty');
        PaymentTransaction::create([
            'uuid' => fake()->uuid(),
            'order_id' => $order->id,
            'gateway' => 'piprapay',
            'provider_transaction_id' => 'pp-paid-1',
            'amount_minor' => $order->total_minor,
            'currency' => 'BDT',
            'status' => 'pending',
            'initiated_at' => now(),
        ]);

        $this->fakePipraPayVerify($order, 'pp-paid-1', 'completed');

        $payload = ['pp_id' => 'pp-paid-1', 'status' => 'completed'];
        $this->postJson('/api/v1/payments/piprapay/webhook', $payload, $this->webhookHeaders())->assertOk();
        $this->postJson('/api/v1/payments/piprapay/webhook', $payload, $this->webhookHeaders())->assertOk();

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(1, Entitlement::where('order_id', $order->id)->count());
        $this->assertSame(1, PaymentEvent::where('gateway', 'piprapay')->where('event_key', 'webhook:pp-paid-1')->count());
        Queue::assertPushed(SendPurchaseConfirmationEmail::class, 1);
    }

    public function test_redirect_after_webhook_completion_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        $this->fakePipraPay();
        $order = $this->pendingOrder();
        $this->fakePipraPayVerify($order, 'pp-paid-2', 'completed');

        $this->postJson('/api/v1/payments/piprapay/webhook', ['pp_id' => 'pp-paid-2'], $this->webhookHeaders())->assertOk();
        $this->postJson('/api/v1/payments/piprapay/success', ['pp_id' => 'pp-paid-2', 'order' => $order->order_number])->assertOk();

        $this->assertSame(1, Entitlement::where('order_id', $order->id)->count());
        Queue::assertPushed(SendPurchaseConfirmationEmail::class, 1);
    }

    public function test_pending_failed_mismatch_unknown_and_malformed_verifications_are_rejected(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->fakePipraPay();
        $order = $this->pendingOrder();

        foreach ([
            ['pending', $order->total_minor, 'BDT', $order->uuid, $order->order_number],
            ['failed', $order->total_minor, 'BDT', $order->uuid, $order->order_number],
            ['completed', 100, 'BDT', $order->uuid, $order->order_number],
            ['completed', $order->total_minor, 'USD', $order->uuid, $order->order_number],
            ['completed', $order->total_minor, 'BDT', fake()->uuid(), 'LBLX-UNKNOWN'],
        ] as $index => [$status, $amountMinor, $currency, $uuid, $number]) {
            Http::fake(['pipra.test/api/verify-payment' => Http::response($this->pipraPayVerifyPayload($number, $uuid, 'pp-bad-'.$index, $status, $amountMinor, $currency))]);
            $this->postJson('/api/v1/payments/piprapay/success', ['pp_id' => 'pp-bad-'.$index, 'order' => $order->order_number])->assertStatus(422);
        }

        $this->postJson('/api/v1/payments/piprapay/webhook', ['status' => 'completed'], $this->webhookHeaders())->assertStatus(422);
        $this->postJson('/api/v1/payments/piprapay/webhook', ['pp_id' => 'pp-bad-key'], ['mh-piprapay-api-key' => 'wrong'])->assertStatus(422);

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_successful_full_refund_revokes_entitlements_and_duplicate_refund_does_not_call_provider_twice(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        $this->fakePipraPay();
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $order = $this->paidOrder('pp-refund-1');

        Http::fake(['pipra.test/api/refund-payment' => Http::response([
            'success' => true,
            'status' => 'refunded',
            'refund_id' => 'refund-1',
            'amount' => '990.00',
            'currency' => 'BDT',
        ])]);

        $this->actingAs($admin)->postJson('/api/v1/admin/orders/'.$order->id.'/refund', ['confirm' => true])->assertOk();

        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame('revoked', Entitlement::where('order_id', $order->id)->firstOrFail()->status);
        $this->assertSame(1, RefundAttempt::where('order_id', $order->id)->where('status', 'succeeded')->count());

        $this->actingAs($admin)->postJson('/api/v1/admin/orders/'.$order->id.'/refund', ['confirm' => true])->assertStatus(422);
        Http::assertSentCount(1);
    }

    public function test_refund_failure_does_not_change_order_or_entitlements(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        $this->fakePipraPay();
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $order = $this->paidOrder('pp-refund-fail');

        Http::fake(['pipra.test/api/refund-payment' => Http::response(['success' => false, 'status' => 'failed'])]);

        $this->actingAs($admin)->postJson('/api/v1/admin/orders/'.$order->id.'/refund', ['confirm' => true])->assertStatus(422);

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('active', Entitlement::where('order_id', $order->id)->firstOrFail()->status);
        $this->assertSame(1, RefundAttempt::where('order_id', $order->id)->where('status', 'failed')->count());
    }

    private function pendingOrder(string $slug = 'ai-automation-n8n'): Order
    {
        $product = Product::where('slug', $slug)->firstOrFail();
        $orderNumber = $this->postJson('/api/v1/checkout/orders', [
            'product_id' => $product->id,
            'customer_name' => 'Guest Buyer',
            'customer_email' => 'guest@example.com',
            'payment_method' => 'card',
        ])->assertCreated()->json('data.order.order_number');

        return Order::where('order_number', $orderNumber)->firstOrFail();
    }

    private function paidOrder(string $ppId): Order
    {
        $order = $this->pendingOrder();
        $this->fakePipraPayVerify($order, $ppId, 'completed');
        $this->postJson('/api/v1/payments/piprapay/webhook', ['pp_id' => $ppId], $this->webhookHeaders())->assertOk();

        return $order->fresh();
    }

    private function fakePipraPay(): void
    {
        config(['services.piprapay.base_url' => 'https://pipra.test', 'services.piprapay.api_key' => 'test-key']);
    }

    private function fakePipraPayVerify(Order $order, string $ppId, string $status): void
    {
        Http::fake(['pipra.test/api/verify-payment' => Http::response(
            $this->pipraPayVerifyPayload($order->order_number, $order->uuid, $ppId, $status, $order->total_minor)
        )]);
    }

    private function pipraPayVerifyPayload(string $orderNumber, string $orderUuid, string $ppId, string $status, int $amountMinor, string $currency = 'BDT'): array
    {
        return [
            'pp_id' => $ppId,
            'transaction_id' => 'txn-'.$ppId,
            'status' => $status,
            'amount' => number_format($amountMinor / 100, 2, '.', ''),
            'currency' => $currency,
            'gateway' => 'bkash',
            'metadata' => [
                'order_uuid' => $orderUuid,
                'order_number' => $orderNumber,
                'payment_attempt_uuid' => fake()->uuid(),
            ],
        ];
    }

    private function webhookHeaders(): array
    {
        return ['mh-piprapay-api-key' => 'test-key'];
    }
}
