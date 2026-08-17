<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\GuestPurchaseClaimService;
use App\Services\PaymentCompletionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminOrderCustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_account_checkouts_record_immutable_checkout_type(): void
    {
        $this->seed(DatabaseSeeder::class);
        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();
        $customer = User::where('email', 'rakib@example.com')->firstOrFail();

        $guest = $this->postJson('/api/v1/checkout/orders', [
            'product_id' => $product->id,
            'customer_name' => 'Guest Buyer',
            'customer_email' => 'new-guest@example.com',
            'customer_phone' => '01700000000',
            'payment_method' => 'piprapay',
        ])->assertCreated()->json('data.order');

        $account = $this->actingAs($customer)->postJson('/api/v1/checkout/orders', [
            'product_id' => $product->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'payment_method' => 'piprapay',
        ])->assertCreated()->json('data.order');

        $this->assertSame('guest', Order::where('order_number', $guest['order_number'])->firstOrFail()->checkout_type);
        $this->assertSame('account', Order::where('order_number', $account['order_number'])->firstOrFail()->checkout_type);
    }

    public function test_guest_order_remains_guest_checkout_after_claim_but_account_status_updates(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();

        $orderResponse = $this->postJson('/api/v1/checkout/orders', [
            'product_id' => $product->id,
            'customer_name' => 'Claim Buyer',
            'customer_email' => 'claim-admin@example.com',
            'customer_phone' => '01800000000',
            'payment_method' => 'piprapay',
        ])->assertCreated()->json('data.order');

        $order = Order::with('items')->where('order_number', $orderResponse['order_number'])->firstOrFail();
        app(PaymentCompletionService::class)->markPaid($order, 'test', 'test:claim-admin', [
            'provider_transaction_id' => 'claim-admin',
            'amount_minor' => $order->total_minor,
            'currency' => $order->currency,
        ]);

        $user = User::factory()->create([
            'email' => 'claim-admin@example.com',
            'email_verified_at' => now(),
            'phone' => '01800000000',
        ]);
        app(GuestPurchaseClaimService::class)->claimForVerifiedUser($user);

        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $this->actingAs($admin)
            ->getJson('/api/v1/admin/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.checkout_type', 'guest')
            ->assertJsonPath('data.checkout_type_label', 'Guest Checkout')
            ->assertJsonPath('data.current_account_status', 'claimed')
            ->assertJsonPath('data.current_account_status_label', 'Claimed');

        $this->assertSame('guest', $order->fresh()->checkout_type);
    }

    public function test_legacy_order_checkout_type_is_unknown_in_admin_payload(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $order = Order::firstOrFail();
        $order->forceFill(['checkout_type' => null])->save();

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.checkout_type', null)
            ->assertJsonPath('data.checkout_type_label', 'Unknown');
    }

    public function test_admin_order_list_and_detail_expose_customer_status_and_phone(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $order = Order::whereNotNull('customer_phone')->latest()->firstOrFail();

        $list = $this->actingAs($admin)->getJson('/api/v1/admin/orders')->assertOk()->json('data.data');
        $this->assertTrue(collect($list)->contains(fn ($row) => isset($row['customer_phone'], $row['checkout_type_label'], $row['current_account_status_label'])));

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.customer_phone', $order->customer_phone)
            ->assertJsonStructure(['data' => ['items', 'payment_transactions', 'entitlements', 'actions']]);
    }

    public function test_admin_customer_list_detail_and_guest_only_rows(): void
    {
        $this->seed(DatabaseSeeder::class);
        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();

        $this->postJson('/api/v1/checkout/orders', [
            'product_id' => $product->id,
            'customer_name' => 'Guest Only',
            'customer_email' => 'guest-only-admin@example.com',
            'customer_phone' => '01900000000',
            'payment_method' => 'piprapay',
        ])->assertCreated();

        $rows = $this->actingAs($admin)->getJson('/api/v1/admin/customers')->assertOk()->json('data.data');
        $guest = collect($rows)->firstWhere('email', 'guest-only-admin@example.com');

        $this->assertSame('No Account', $guest['account_status_label']);
        $this->assertSame('01900000000', $guest['phone']);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/customers/'.$guest['customer_key'])
            ->assertOk()
            ->assertJsonPath('data.summary.email', 'guest-only-admin@example.com')
            ->assertJsonPath('data.summary.account_status_label', 'No Account')
            ->assertJsonCount(1, 'data.orders');
    }

    public function test_registered_customer_can_be_suspended_and_reactivated(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $customer = User::where('email', 'rakib@example.com')->firstOrFail();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/customers/'.$customer->id.'/suspend')
            ->assertOk()
            ->assertJsonPath('data.account_status', 'suspended');

        $this->assertSame('suspended', $customer->fresh()->status);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/customers/'.$customer->id.'/reactivate')
            ->assertOk()
            ->assertJsonPath('data.account_status', 'active');

        $this->assertSame('active', $customer->fresh()->status);
    }

    public function test_unpaid_order_can_be_cancelled_but_paid_order_cannot_be_marked_paid_or_cancelled(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();
        $pendingResponse = $this->postJson('/api/v1/checkout/orders', [
            'product_id' => $product->id,
            'customer_name' => 'Pending Admin',
            'customer_email' => 'pending-admin@example.com',
            'customer_phone' => '01600000000',
            'payment_method' => 'piprapay',
        ])->assertCreated()->json('data.order');
        $pending = Order::where('order_number', $pendingResponse['order_number'])->firstOrFail();
        $paid = Order::where('payment_status', 'paid')->firstOrFail();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/orders/'.$pending->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'cancelled')
            ->assertJsonPath('data.order_status', 'cancelled');

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/orders/'.$paid->id.'/cancel')
            ->assertStatus(422);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/orders/'.$pending->id.'/mark-paid')
            ->assertNotFound();
    }

    public function test_admin_authorization_and_public_privacy_boundaries_hold(): void
    {
        $this->seed(DatabaseSeeder::class);
        $customer = User::where('email', 'rakib@example.com')->firstOrFail();

        $this->getJson('/api/v1/admin/orders')->assertUnauthorized();
        $this->actingAs($customer)->getJson('/api/v1/admin/customers')->assertForbidden();

        $this->getJson('/api/v1/tracking/config')
            ->assertOk()
            ->assertJsonMissing(['customer_phone' => $customer->phone])
            ->assertJsonMissing(['customer_email' => $customer->email]);

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonMissing(['customer_phone' => $customer->phone])
            ->assertJsonMissing(['customer_email' => $customer->email]);
    }
}
