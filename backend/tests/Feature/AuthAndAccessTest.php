<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthAndAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_login_logout_and_bad_credentials(): void
    {
        $this->seed(DatabaseSeeder::class);
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'New Customer',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()->assertJsonPath('data.email', 'new@example.com');

        $this->postJson('/api/v1/auth/logout')->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'new@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'new@example.com',
            'password' => 'password123',
        ])->assertOk();
    }

    public function test_authenticated_user_cannot_call_login_or_register_endpoints(): void
    {
        $this->seed(DatabaseSeeder::class);

        $customer = User::where('email', 'rakib@example.com')->firstOrFail();

        $this->actingAs($customer)->postJson('/api/v1/auth/login', [
            'email' => 'other@example.com',
            'password' => 'password123',
        ])->assertStatus(409)->assertJsonPath('message', 'Already authenticated.');

        $this->actingAs($customer)->postJson('/api/v1/auth/register', [
            'name' => 'Duplicate Customer',
            'email' => 'duplicate@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(409)->assertJsonPath('message', 'Already authenticated.');

        $this->assertDatabaseMissing('users', ['email' => 'duplicate@example.com']);
    }

    public function test_guest_cannot_access_account_and_customer_cannot_access_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->getJson('/api/v1/account/library')->assertUnauthorized();

        $customer = User::where('email', 'rakib@example.com')->firstOrFail();
        $this->actingAs($customer)->getJson('/api/v1/admin/products')->assertForbidden();
    }

    public function test_admin_can_access_admin_api(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $this->actingAs($admin)->getJson('/api/v1/admin/products')->assertOk();
    }

    public function test_email_verification_claims_guest_purchases_idempotently(): void
    {
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
        Queue::fake();
        $this->fakePipraPay();
        $guestEmail = 'buyer@example.com';
        $productId = \App\Models\Product::where('slug', 'ai-automation-n8n')->value('id');

        $orderNumber = $this->postJson('/api/v1/checkout/orders', [
            'product_id' => $productId,
            'customer_name' => 'Guest Buyer',
            'customer_email' => $guestEmail,
            'payment_method' => 'card',
        ])->assertCreated()->json('data.order.order_number');

        $order = \App\Models\Order::where('order_number', $orderNumber)->firstOrFail();
        $this->fakePipraPayResponse($order, 'claim-test');
        $this->postJson('/api/v1/payments/piprapay/webhook', [
            'pp_id' => 'claim-test',
        ], $this->webhookHeaders())->assertOk();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Guest Buyer',
            'email' => $guestEmail,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::where('email', $guestEmail)->firstOrFail();
        $this->actingAs($user)->getJson('/api/v1/account/library')->assertJsonCount(0, 'data');

        $verifyUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->getJson($verifyUrl)->assertOk()->assertJsonPath('data.orders_claimed', 1);
        $this->getJson($verifyUrl)->assertOk()->assertJsonPath('data.orders_claimed', 0);

        $this->actingAs($user)->getJson('/api/v1/account/library')->assertJsonCount(1, 'data');
    }

    public function test_different_verified_email_does_not_claim_guest_purchase(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        $this->fakePipraPay();
        $productId = \App\Models\Product::where('slug', 'ai-automation-n8n')->value('id');

        $orderNumber = $this->postJson('/api/v1/checkout/orders', [
            'product_id' => $productId,
            'customer_name' => 'Guest Buyer',
            'customer_email' => 'buyer@example.com',
            'payment_method' => 'card',
        ])->assertCreated()->json('data.order.order_number');

        $order = \App\Models\Order::where('order_number', $orderNumber)->firstOrFail();
        $this->fakePipraPayResponse($order, 'different-email');
        $this->postJson('/api/v1/payments/piprapay/webhook', [
            'pp_id' => 'different-email',
        ], $this->webhookHeaders())->assertOk();

        $user = User::create([
            'name' => 'Other Buyer',
            'email' => 'other@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
        ]);
        $user->roles()->syncWithoutDetaching(Role::where('name', 'customer')->value('id'));

        $this->actingAs($user)->getJson('/api/v1/account/library')->assertJsonCount(0, 'data');
    }

    private function fakePipraPay(): void
    {
        config(['services.piprapay.base_url' => 'https://pipra.test', 'services.piprapay.api_key' => 'test-key']);
    }

    private function fakePipraPayResponse(\App\Models\Order $order, string $ppId): void
    {
        Http::fake([
            'pipra.test/api/verify-payment' => Http::response([
                'status' => 'completed',
                'pp_id' => $ppId,
                'transaction_id' => 'txn-'.$ppId,
                'amount' => number_format($order->total_minor / 100, 2, '.', ''),
                'currency' => 'BDT',
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
