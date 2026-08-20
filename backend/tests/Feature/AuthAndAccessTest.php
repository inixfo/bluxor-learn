<?php

namespace Tests\Feature;

use App\Models\Entitlement;
use App\Models\GuestAccessToken;
use App\Models\MetaConversionEvent;
use App\Models\Order;
use App\Models\PaymentEvent;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\PaymentCompletionService;
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

    public function test_unverified_registration_alone_does_not_claim_guest_purchase(): void
    {
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
        $guest = $this->paidGuestOrder('buyer-register@example.com', 'register-no-claim');

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Guest Buyer',
            'email' => 'buyer-register@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::where('email', 'buyer-register@example.com')->firstOrFail();

        $this->assertNull($guest['order']->fresh()->user_id);
        $this->assertNull(Entitlement::where('order_id', $guest['order']->id)->firstOrFail()->user_id);
        $this->actingAs($user)->getJson('/api/v1/account/library')->assertJsonCount(0, 'data');
    }

    public function test_valid_guest_token_matching_account_email_claims_purchase_without_email_verification(): void
    {
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
        $guest = $this->paidGuestOrder('buyer-token@example.com', 'token-claim');

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Guest Buyer',
            'email' => 'buyer-token@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::where('email', 'buyer-token@example.com')->firstOrFail();
        $paymentEvents = PaymentEvent::count();
        $metaEvents = MetaConversionEvent::where('event_name', 'Purchase')->count();
        $entitlements = Entitlement::where('order_id', $guest['order']->id)->count();

        $this->actingAs($user)->postJson('/api/v1/account/claim-purchase', [
            'order_number' => $guest['order']->order_number,
            'guest_access_token' => $guest['token'],
        ])->assertOk()
            ->assertJsonPath('data.orders_claimed', 1)
            ->assertJsonPath('data.entitlements_claimed', 1);

        $order = $guest['order']->fresh();
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame('guest', $order->checkout_type);
        $this->assertSame($user->id, Entitlement::where('order_id', $order->id)->firstOrFail()->user_id);
        $this->assertSame($entitlements, Entitlement::where('order_id', $order->id)->count());
        $this->assertSame($paymentEvents, PaymentEvent::count());
        $this->assertSame($metaEvents, MetaConversionEvent::where('event_name', 'Purchase')->count());

        $this->actingAs($user)->postJson('/api/v1/account/claim-purchase', [
            'order_number' => $order->order_number,
            'guest_access_token' => $guest['token'],
        ])->assertOk()->assertJsonPath('data.orders_claimed', 0);

        $this->actingAs($user)->getJson('/api/v1/account/orders')->assertOk()->assertJsonFragment(['order_number' => $order->order_number]);
        $this->actingAs($user)->getJson('/api/v1/account/library')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($user)->getJson('/api/v1/account/downloads')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_guest_token_claim_rejects_wrong_email_invalid_token_and_unpaid_order(): void
    {
        $this->seed(DatabaseSeeder::class);
        $guest = $this->paidGuestOrder('buyer-secure@example.com', 'secure-claim');
        $wrongUser = $this->customer('wrong-secure@example.com', verified: false);
        $matchingUser = $this->customer('buyer-secure@example.com', verified: false);
        $pending = $this->pendingGuestOrder('buyer-secure@example.com');

        $this->actingAs($wrongUser)->postJson('/api/v1/account/claim-purchase', [
            'order_number' => $guest['order']->order_number,
            'guest_access_token' => $guest['token'],
        ])->assertStatus(422);

        $this->actingAs($matchingUser)->postJson('/api/v1/account/claim-purchase', [
            'order_number' => $guest['order']->order_number,
            'guest_access_token' => 'not-the-token',
        ])->assertForbidden();

        $this->actingAs($matchingUser)->postJson('/api/v1/account/claim-purchase', [
            'order_number' => $pending['order']->order_number,
            'guest_access_token' => $pending['token'],
        ])->assertForbidden();

        $this->assertNull($guest['order']->fresh()->user_id);
        $this->assertNull($pending['order']->fresh()->user_id);
    }

    public function test_revoked_or_expired_guest_token_cannot_claim_purchase(): void
    {
        $this->seed(DatabaseSeeder::class);
        $revoked = $this->paidGuestOrder('buyer-revoked@example.com', 'revoked-claim');
        $expired = $this->paidGuestOrder('buyer-expired@example.com', 'expired-claim');
        $revokedUser = $this->customer('buyer-revoked@example.com', verified: false);
        $expiredUser = $this->customer('buyer-expired@example.com', verified: false);

        GuestAccessToken::where('order_id', $revoked['order']->id)->update(['revoked_at' => now()]);
        GuestAccessToken::where('order_id', $expired['order']->id)->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($revokedUser)->postJson('/api/v1/account/claim-purchase', [
            'order_number' => $revoked['order']->order_number,
            'guest_access_token' => $revoked['token'],
        ])->assertForbidden();

        $this->actingAs($expiredUser)->postJson('/api/v1/account/claim-purchase', [
            'order_number' => $expired['order']->order_number,
            'guest_access_token' => $expired['token'],
        ])->assertForbidden();
    }

    public function test_verified_user_login_auto_claims_matching_paid_guest_purchases(): void
    {
        $this->seed(DatabaseSeeder::class);
        $guest = $this->paidGuestOrder('login-claim@example.com', 'login-claim');
        $this->customer('login-claim@example.com');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'login-claim@example.com',
            'password' => 'password123',
        ])->assertOk();

        $this->assertSame(
            User::where('email', 'login-claim@example.com')->value('id'),
            $guest['order']->fresh()->user_id
        );
    }

    public function test_auth_me_self_heals_unclaimed_guest_purchase_for_verified_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        $guest = $this->paidGuestOrder('me-claim@example.com', 'me-claim');
        $user = $this->customer('me-claim@example.com');

        $this->actingAs($user)->getJson('/api/v1/auth/me')->assertOk();

        $this->assertSame($user->id, $guest['order']->fresh()->user_id);
    }

    public function test_email_verification_claims_all_matching_paid_guest_purchases_idempotently(): void
    {
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
        $first = $this->paidGuestOrder('buyer-verify@example.com', 'verify-one');
        $second = $this->paidGuestOrder('BUYER-VERIFY@example.com', 'verify-two');
        $pending = $this->pendingGuestOrder('buyer-verify@example.com');
        $user = $this->customer('buyer-verify@example.com', verified: false);
        $paymentEvents = PaymentEvent::count();

        $verifyUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->getJson($verifyUrl)->assertOk()
            ->assertJsonPath('data.orders_claimed', 2)
            ->assertJsonPath('data.entitlements_claimed', 2);
        $this->getJson($verifyUrl)->assertOk()->assertJsonPath('data.orders_claimed', 0);

        $this->assertSame($user->id, $first['order']->fresh()->user_id);
        $this->assertSame($user->id, $second['order']->fresh()->user_id);
        $this->assertNull($pending['order']->fresh()->user_id);
        $this->assertSame('guest', $first['order']->fresh()->checkout_type);
        $this->assertSame($paymentEvents, PaymentEvent::count());
        $this->actingAs($user)->getJson('/api/v1/account/library')->assertJsonCount(1, 'data');
    }

    public function test_google_verified_account_claims_matching_guest_purchases(): void
    {
        $this->seed(DatabaseSeeder::class);
        $guest = $this->paidGuestOrder('google-claim@example.com', 'google-claim');
        config([
            'services.google.client_id' => 'google-client',
            'services.google.client_secret' => 'google-secret',
            'services.google.redirect_uri' => 'http://localhost/api/v1/auth/google/callback',
            'app.frontend_url' => 'http://localhost',
        ]);

        $redirectUrl = $this->getJson('/api/v1/auth/google/redirect?return_to=/account')
            ->assertOk()
            ->json('data.url');
        parse_str(parse_url($redirectUrl, PHP_URL_QUERY), $query);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access-token']),
            'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'sub' => 'google-user-1',
                'email' => 'google-claim@example.com',
                'email_verified' => true,
                'name' => 'Google Buyer',
            ]),
        ]);

        $this->get('/api/v1/auth/google/callback?code=valid-code&state='.$query['state'])
            ->assertRedirect('http://localhost/account');

        $user = User::where('email', 'google-claim@example.com')->firstOrFail();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame($user->id, $guest['order']->fresh()->user_id);
    }

    public function test_different_verified_email_does_not_claim_guest_purchase(): void
    {
        $this->seed(DatabaseSeeder::class);
        $guest = $this->paidGuestOrder('buyer-different@example.com', 'different-email');
        $user = $this->customer('other-different@example.com');

        $this->actingAs($user)->getJson('/api/v1/account/library')->assertJsonCount(0, 'data');
        $this->assertNull($guest['order']->fresh()->user_id);
    }

    public function test_explicit_bulk_claim_endpoint_requires_verified_email_and_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $first = $this->paidGuestOrder('bulk-claim@example.com', 'bulk-one');
        $second = $this->paidGuestOrder('bulk-claim@example.com', 'bulk-two');
        $unverified = $this->customer('bulk-claim@example.com', verified: false);

        $this->actingAs($unverified)->postJson('/api/v1/account/claim-purchases')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Verify your email before claiming purchases.');

        $unverified->forceFill(['email' => 'bulk-unverified@example.com'])->save();
        $verified = $this->customer('bulk-claim@example.com');

        $this->actingAs($verified)->postJson('/api/v1/account/claim-purchases')->assertOk()
            ->assertJsonPath('data.orders_claimed', 2)
            ->assertJsonPath('data.entitlements_claimed', 2);
        $this->actingAs($verified)->postJson('/api/v1/account/claim-purchases')->assertOk()
            ->assertJsonPath('data.orders_claimed', 0)
            ->assertJsonPath('data.entitlements_claimed', 0);

        $this->assertSame($verified->id, $first['order']->fresh()->user_id);
        $this->assertSame($verified->id, $second['order']->fresh()->user_id);
        $this->actingAs($verified)->getJson('/api/v1/account/downloads')->assertJsonCount(1, 'data');
    }

    public function test_claim_service_does_not_attach_entitlements_from_another_users_order(): void
    {
        $this->seed(DatabaseSeeder::class);
        $guest = $this->paidGuestOrder('shared-email@example.com', 'shared-email');
        $otherUser = $this->customer('other-owner@example.com');
        $otherOrder = $this->paidGuestOrder('shared-email@example.com', 'other-owner')['order'];
        $otherOrder->forceFill(['user_id' => $otherUser->id])->save();
        Entitlement::where('order_id', $otherOrder->id)->update(['user_id' => null]);

        $user = $this->customer('shared-email@example.com');
        $this->actingAs($user)->postJson('/api/v1/account/claim-purchases')->assertOk()
            ->assertJsonPath('data.orders_claimed', 1)
            ->assertJsonPath('data.entitlements_claimed', 1);

        $this->assertSame($user->id, $guest['order']->fresh()->user_id);
        $this->assertSame($otherUser->id, $otherOrder->fresh()->user_id);
        $this->assertNull(Entitlement::where('order_id', $otherOrder->id)->firstOrFail()->user_id);
    }

    public function test_verified_guest_purchase_backfill_command_supports_dry_run_and_real_execution(): void
    {
        $this->seed(DatabaseSeeder::class);
        $guest = $this->paidGuestOrder('command-claim@example.com', 'command-claim');
        $user = $this->customer('command-claim@example.com');

        $this->artisan('purchases:claim-verified-guests --dry-run')
            ->expectsOutputToContain('Verified users checked:')
            ->expectsOutputToContain('Matching paid guest orders:')
            ->expectsOutputToContain('Potential entitlements:')
            ->assertExitCode(0);
        $this->assertNull($guest['order']->fresh()->user_id);

        $this->artisan('purchases:claim-verified-guests')
            ->expectsOutputToContain('Orders claimed:')
            ->expectsOutputToContain('Entitlements claimed:')
            ->assertExitCode(0);

        $this->assertSame($user->id, $guest['order']->fresh()->user_id);
    }

    private function paidGuestOrder(string $email, string $eventKey): array
    {
        Queue::fake();
        $pending = $this->pendingGuestOrder($email);
        app(PaymentCompletionService::class)->markPaid($pending['order'], 'test', 'test:'.$eventKey, [
            'provider_transaction_id' => $eventKey,
            'amount_minor' => $pending['order']->total_minor,
            'currency' => $pending['order']->currency,
        ]);

        return ['order' => $pending['order']->fresh(['items', 'entitlements']), 'token' => $pending['token'], 'product' => $pending['product']];
    }

    private function pendingGuestOrder(string $email): array
    {
        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();
        $payload = $this->postJson('/api/v1/checkout/orders', [
            'product_id' => $product->id,
            'customer_name' => 'Guest Buyer',
            'customer_email' => $email,
            'payment_method' => 'card',
        ])->assertCreated()->json('data');

        return [
            'order' => Order::where('order_number', $payload['order']['order_number'])->firstOrFail(),
            'token' => $payload['guest_access_token'],
            'product' => $product,
        ];
    }

    private function customer(string $email, bool $verified = true): User
    {
        $user = User::factory()->create([
            'name' => 'Claim Customer',
            'email' => strtolower($email),
            'email_verified_at' => $verified ? now() : null,
            'password' => Hash::make('password123'),
        ]);
        $user->roles()->syncWithoutDetaching(Role::firstOrCreate(['name' => 'customer'])->id);

        return $user;
    }

    private function fakePipraPay(): void
    {
        config(['services.piprapay.base_url' => 'https://pipra.test', 'services.piprapay.api_key' => 'test-key']);
    }

    private function fakePipraPayResponse(Order $order, string $ppId): void
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
