<?php

namespace Tests\Feature;

use App\Models\Entitlement;
use App\Models\GuestAccessToken;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class DownloadAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_entitled_customer_gets_signed_download_and_can_use_it(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        $this->fakePipraPay();
        $this->useTempPrivateDisk();

        $customer = User::where('email', 'rakib@example.com')->firstOrFail();
        $file = Product::where('slug', 'ai-automation-n8n')->firstOrFail()->files()->firstOrFail();
        Storage::disk('private')->put($file->storage_path, 'download-body');

        $payload = $this->actingAs($customer)
            ->postJson('/api/v1/account/downloads/'.$file->id)
            ->assertOk()
            ->json('data');

        $this->assertRelativeDownloadUrl($payload['download_url']);
        $this->actingAs($customer)->get($payload['download_url'])->assertOk();
        $this->withServerVariables(['HTTP_HOST' => 'internal-nginx.test', 'HTTPS' => 'off'])
            ->actingAs($customer)
            ->get($payload['download_url'])
            ->assertOk();
    }

    public function test_unrelated_customer_cannot_download_another_customers_file(): void
    {
        $this->seed(DatabaseSeeder::class);
        $other = User::factory()->create(['email' => 'other-customer@example.com']);
        $file = ProductFile::firstOrFail();

        $this->actingAs($other)->postJson('/api/v1/account/downloads/'.$file->id)->assertForbidden();
    }

    public function test_guest_signed_access_works_only_for_paid_non_expired_order(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        $this->fakePipraPay();
        $this->useTempPrivateDisk();

        $product = Product::where('slug', 'practical-bug-bounty')->firstOrFail();
        $file = $product->files()->firstOrFail();
        Storage::disk('private')->put($file->storage_path, 'guest-download-body');

        $orderResponse = $this->postJson('/api/v1/checkout/orders', [
            'product_id' => $product->id,
            'customer_name' => 'Guest Buyer',
            'customer_email' => 'guest-access@example.com',
            'payment_method' => 'card',
        ])->assertCreated()->json('data');

        $this->getJson('/api/v1/guest/orders/'.$orderResponse['order']['order_number'].'?guest_access_token='.$orderResponse['guest_access_token'])
            ->assertForbidden();

        $order = \App\Models\Order::where('order_number', $orderResponse['order']['order_number'])->firstOrFail();
        $this->fakePipraPayResponse($order, 'guest-access-paid');
        $this->postJson('/api/v1/payments/piprapay/webhook', [
            'pp_id' => 'guest-access-paid',
        ], $this->webhookHeaders())->assertOk();

        $downloads = $this->getJson('/api/v1/guest/orders/'.$orderResponse['order']['order_number'].'?guest_access_token='.$orderResponse['guest_access_token'])
            ->assertOk()
            ->json('data.downloads');

        $this->assertRelativeDownloadUrl($downloads[0]['download_url']);
        $this->get($downloads[0]['download_url'])->assertOk();
        $this->withServerVariables(['HTTP_HOST' => 'internal-nginx.test', 'HTTPS' => 'off'])
            ->get($downloads[0]['download_url'])
            ->assertOk();
    }

    public function test_guest_download_rejects_tampered_url_parts_and_signature(): void
    {
        $fixture = $this->paidGuestDownloadFixture();
        $otherProduct = Product::where('id', '!=', $fixture['product']->id)->whereHas('files')->firstOrFail();
        $otherFile = $otherProduct->files()->firstOrFail();
        Storage::disk('private')->put($otherFile->storage_path, 'other-file-body');
        $otherEntitlement = Entitlement::where('id', '!=', $fixture['entitlement']->id)->firstOrFail();

        $this->get($this->replaceDownloadPathIds($fixture['url'], fileId: $otherFile->id))->assertForbidden();
        $this->get($this->replaceDownloadPathIds($fixture['url'], entitlementId: $otherEntitlement->id))->assertForbidden();
        $this->get($this->tamperSignature($fixture['url']))->assertForbidden();
    }

    public function test_guest_download_rejects_invalid_guest_tokens_and_payment_state(): void
    {
        $fixture = $this->paidGuestDownloadFixture();

        $this->get($this->signedGuestUrl($fixture['file'], $fixture['entitlement'], 'wrong-token'))->assertForbidden();

        GuestAccessToken::where('order_id', $fixture['order']->id)->update(['revoked_at' => now()]);
        $this->get($this->signedGuestUrl($fixture['file'], $fixture['entitlement'], $fixture['token']))->assertForbidden();

        GuestAccessToken::where('order_id', $fixture['order']->id)->update(['revoked_at' => null, 'expires_at' => now()->subMinute()]);
        $this->get($this->signedGuestUrl($fixture['file'], $fixture['entitlement'], $fixture['token']))->assertForbidden();

        $unpaid = $this->unpaidGuestDownloadFixture();
        $this->get($unpaid['url'])->assertForbidden();
    }

    public function test_guest_download_rejects_expired_signature_and_invalid_resource_state(): void
    {
        $fixture = $this->paidGuestDownloadFixture();

        $this->get($this->signedGuestUrl($fixture['file'], $fixture['entitlement'], $fixture['token'], now()->subMinute()))->assertForbidden();

        $fixture['entitlement']->forceFill(['status' => 'inactive'])->save();
        $this->get($this->signedGuestUrl($fixture['file'], $fixture['entitlement'], $fixture['token']))->assertForbidden();
        $fixture['entitlement']->forceFill(['status' => 'active'])->save();

        $fixture['file']->forceFill(['status' => 'inactive'])->save();
        $this->get($this->signedGuestUrl($fixture['file'], $fixture['entitlement'], $fixture['token']))->assertForbidden();
        $fixture['file']->forceFill(['status' => 'active'])->save();

        $otherFile = Product::where('id', '!=', $fixture['product']->id)->whereHas('files')->firstOrFail()->files()->firstOrFail();
        Storage::disk('private')->put($otherFile->storage_path, 'mismatch-body');
        $this->get($this->signedGuestUrl($otherFile, $fixture['entitlement'], $fixture['token']))->assertForbidden();
    }

    public function test_customer_download_rejects_tampered_and_expired_signatures(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        $this->fakePipraPay();
        $this->useTempPrivateDisk();

        $customer = User::where('email', 'rakib@example.com')->firstOrFail();
        $file = Product::where('slug', 'ai-automation-n8n')->firstOrFail()->files()->firstOrFail();
        Storage::disk('private')->put($file->storage_path, 'download-body');

        $payload = $this->actingAs($customer)
            ->postJson('/api/v1/account/downloads/'.$file->id)
            ->assertOk()
            ->json('data');

        $entitlement = Entitlement::findOrFail($payload['file']['entitlement_id']);
        $this->actingAs($customer)->get($this->tamperSignature($payload['download_url']))->assertForbidden();
        $this->actingAs($customer)
            ->get($this->signedCustomerUrl($file, $entitlement, now()->subMinute()))
            ->assertForbidden();
    }

    public function test_expired_guest_access_fails(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        $this->fakePipraPay();
        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();

        $orderResponse = $this->postJson('/api/v1/checkout/orders', [
            'product_id' => $product->id,
            'customer_name' => 'Guest Buyer',
            'customer_email' => 'expired-guest@example.com',
            'payment_method' => 'card',
        ])->assertCreated()->json('data');

        $order = \App\Models\Order::where('order_number', $orderResponse['order']['order_number'])->firstOrFail();
        $this->fakePipraPayResponse($order, 'expired-access');
        $this->postJson('/api/v1/payments/piprapay/webhook', [
            'pp_id' => 'expired-access',
        ], $this->webhookHeaders())->assertOk();

        GuestAccessToken::query()->update(['expires_at' => now()->subMinute()]);

        $this->getJson('/api/v1/guest/orders/'.$orderResponse['order']['order_number'].'?guest_access_token='.$orderResponse['guest_access_token'])
            ->assertForbidden();
    }

    private function useTempPrivateDisk(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'learn_bluxor_private_test_'.uniqid();
        File::ensureDirectoryExists($root);
        config(['filesystems.disks.private.root' => $root]);
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

    private function paidGuestDownloadFixture(string $productSlug = 'practical-bug-bounty', string $email = 'guest-access@example.com'): array
    {
        if (! Product::exists()) {
            $this->seed(DatabaseSeeder::class);
        }

        Queue::fake();
        $this->fakePipraPay();
        $this->useTempPrivateDisk();

        $product = Product::where('slug', $productSlug)->firstOrFail();
        $file = $product->files()->firstOrFail();
        Storage::disk('private')->put($file->storage_path, 'guest-download-body');

        $orderResponse = $this->postJson('/api/v1/checkout/orders', [
            'product_id' => $product->id,
            'customer_name' => 'Guest Buyer',
            'customer_email' => $email,
            'payment_method' => 'card',
        ])->assertCreated()->json('data');

        $order = Order::where('order_number', $orderResponse['order']['order_number'])->firstOrFail();
        $paymentId = 'paid-'.Str::random(12);
        $this->fakePipraPayResponse($order, $paymentId);
        $this->postJson('/api/v1/payments/piprapay/webhook', [
            'pp_id' => $paymentId,
        ], $this->webhookHeaders())->assertOk();

        $order->refresh()->load('entitlements');
        $entitlement = $order->entitlements()->firstOrFail();
        $downloads = $this->getJson('/api/v1/guest/orders/'.$order->order_number.'?guest_access_token='.$orderResponse['guest_access_token'])
            ->assertOk()
            ->json('data.downloads');

        $this->assertRelativeDownloadUrl($downloads[0]['download_url']);

        return [
            'product' => $product,
            'file' => $file,
            'order' => $order,
            'entitlement' => $entitlement,
            'token' => $orderResponse['guest_access_token'],
            'url' => $downloads[0]['download_url'],
        ];
    }

    private function unpaidGuestDownloadFixture(): array
    {
        if (! Product::exists()) {
            $this->seed(DatabaseSeeder::class);
        }

        Queue::fake();
        $this->fakePipraPay();
        $this->useTempPrivateDisk();

        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();
        $file = $product->files()->firstOrFail();
        Storage::disk('private')->put($file->storage_path, 'unpaid-body');

        $orderResponse = $this->postJson('/api/v1/checkout/orders', [
            'product_id' => $product->id,
            'customer_name' => 'Guest Buyer',
            'customer_email' => 'unpaid-guest@example.com',
            'payment_method' => 'card',
        ])->assertCreated()->json('data');

        $order = Order::with('items')->where('order_number', $orderResponse['order']['order_number'])->firstOrFail();
        $entitlement = Entitlement::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => null,
            'order_id' => $order->id,
            'order_item_id' => $order->items->first()->id,
            'product_id' => $product->id,
            'customer_email' => $order->customer_email,
            'status' => 'active',
            'granted_at' => now(),
        ]);

        return [
            'file' => $file,
            'order' => $order,
            'entitlement' => $entitlement,
            'token' => $orderResponse['guest_access_token'],
            'url' => $this->signedGuestUrl($file, $entitlement, $orderResponse['guest_access_token']),
        ];
    }

    private function signedGuestUrl(ProductFile $file, Entitlement $entitlement, string $token, mixed $expiresAt = null): string
    {
        return URL::temporarySignedRoute('downloads.guest', $expiresAt ?? now()->addMinutes(10), [
            'file' => $file->id,
            'entitlement' => $entitlement->id,
            'token' => $token,
            'nonce' => Str::random(16),
        ], absolute: false);
    }

    private function signedCustomerUrl(ProductFile $file, Entitlement $entitlement, mixed $expiresAt = null): string
    {
        return URL::temporarySignedRoute('downloads.customer', $expiresAt ?? now()->addMinutes(10), [
            'file' => $file->id,
            'entitlement' => $entitlement->id,
            'nonce' => Str::random(16),
        ], absolute: false);
    }

    private function assertRelativeDownloadUrl(string $url): void
    {
        $this->assertStringStartsWith('/api/v1/', $url);
        $this->assertFalse(str_starts_with($url, 'http://'));
        $this->assertFalse(str_starts_with($url, 'https://'));
    }

    private function replaceDownloadPathIds(string $url, ?int $fileId = null, ?int $entitlementId = null): string
    {
        $parts = parse_url($url);
        $segments = explode('/', $parts['path']);

        if ($fileId !== null) {
            $segments[count($segments) - 2] = (string) $fileId;
        }

        if ($entitlementId !== null) {
            $segments[count($segments) - 1] = (string) $entitlementId;
        }

        return implode('/', $segments).(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    private function tamperSignature(string $url): string
    {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);
        $signature = (string) ($query['signature'] ?? '');
        $query['signature'] = ($signature[0] ?? 'a') === 'a' ? 'b'.substr($signature, 1) : 'a'.substr($signature, 1);

        return $parts['path'].'?'.http_build_query($query);
    }
}
