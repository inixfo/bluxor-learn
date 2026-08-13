<?php

namespace Tests\Feature;

use App\Models\GuestAccessToken;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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

        $url = parse_url($payload['download_url']);
        $this->actingAs($customer)->get($url['path'].'?'.$url['query'])->assertOk();
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

        $url = parse_url($downloads[0]['download_url']);
        $this->get($url['path'].'?'.$url['query'])->assertOk();
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
}
