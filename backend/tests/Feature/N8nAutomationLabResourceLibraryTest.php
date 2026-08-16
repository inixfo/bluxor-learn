<?php

namespace Tests\Feature;

use App\Models\Entitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\GuestAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class N8nAutomationLabResourceLibraryTest extends TestCase
{
    use RefreshDatabase;

    private const PRODUCT_SLUG = 'n8n-automation-lab-bangla';
    private const SAMPLE_PROJECT = 'project-17';
    private const SAMPLE_FILE = 'project17_demo_lead_form.html';

    public function test_public_manifest_is_visible_but_download_links_are_hidden_without_entitlement(): void
    {
        $this->createProduct();

        $response = $this->getJson('/api/v1/public-resource-library/n8n-automation-lab')
            ->assertOk()
            ->assertJsonPath('data.authorized', false)
            ->assertJsonPath('data.product.slug', self::PRODUCT_SLUG);

        $data = $response->json('data');

        $this->assertCount(30, $data['projects']);
        $this->assertArrayNotHasKey('download_url', $data['master_pack']);
        $this->assertArrayNotHasKey('public_file', $data['master_pack']);
        $this->assertSame([], $data['projects'][0]['resources']);
        $this->assertNotEmpty($data['projects'][0]['resource_types']);
    }

    public function test_unauthorized_downloads_are_forbidden(): void
    {
        $this->createProduct();

        $this->get('/resources/n8n-automation-lab/download/master-pack')->assertForbidden();
        $this->get('/resources/n8n-automation-lab/download/'.self::SAMPLE_PROJECT.'/'.self::SAMPLE_FILE)->assertForbidden();
    }

    public function test_entitled_customer_can_see_and_download_project_resources_and_master_pack(): void
    {
        $product = $this->createProduct();
        $user = User::factory()->create(['email' => 'n8n-buyer@example.com']);
        $this->grantEntitlement($product, $user);

        $data = $this->actingAs($user)
            ->getJson('/api/v1/public-resource-library/n8n-automation-lab')
            ->assertOk()
            ->assertJsonPath('data.authorized', true)
            ->json('data');

        $project = collect($data['projects'])->firstWhere('slug', self::SAMPLE_PROJECT);
        $resource = collect($project['resources'])->firstWhere('public_file', self::SAMPLE_FILE);

        $this->assertNotEmpty($data['master_pack']['download_url']);
        $this->assertNotEmpty($resource['download_url']);

        $this->actingAs($user)
            ->get($resource['download_url'])
            ->assertOk()
            ->assertDownload(self::SAMPLE_FILE);

        $this->actingAs($user)
            ->get($data['master_pack']['download_url'])
            ->assertOk()
            ->assertDownload('n8n-automation-lab-resources.zip');
    }

    public function test_guest_buyer_can_use_order_number_and_guest_token(): void
    {
        $product = $this->createProduct();
        $order = $this->grantEntitlement($product, null, 'guest-n8n@example.com');
        $token = app(GuestAccessService::class)->issue($order);

        $query = '?order_number='.$order->order_number.'&guest_access_token='.$token;

        $data = $this->getJson('/api/v1/public-resource-library/n8n-automation-lab'.$query)
            ->assertOk()
            ->assertJsonPath('data.authorized', true)
            ->json('data');

        $project = collect($data['projects'])->firstWhere('slug', self::SAMPLE_PROJECT);
        $resource = collect($project['resources'])->firstWhere('public_file', self::SAMPLE_FILE);

        $this->assertStringContainsString('order_number='.$order->order_number, $resource['download_url']);
        $this->assertStringContainsString('guest_access_token=', $resource['download_url']);

        $this->get($resource['download_url'])
            ->assertOk()
            ->assertDownload(self::SAMPLE_FILE);
    }

    public function test_unknown_and_traversal_download_paths_are_rejected(): void
    {
        $product = $this->createProduct();
        $user = User::factory()->create();
        $this->grantEntitlement($product, $user);

        $this->actingAs($user)
            ->get('/resources/n8n-automation-lab/download/'.self::SAMPLE_PROJECT.'/missing.json')
            ->assertNotFound();

        $this->actingAs($user)
            ->get('/resources/n8n-automation-lab/download/'.self::SAMPLE_PROJECT.'/..%2Fmanifest.json')
            ->assertNotFound();
    }

    private function createProduct(): Product
    {
        return Product::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'n8n Automation Lab বাংলা',
            'slug' => self::PRODUCT_SLUG,
            'product_type' => 'ebook',
            'status' => 'published',
            'regular_price_minor' => 100000,
            'currency' => 'BDT',
            'published_at' => now(),
        ]);
    }

    private function grantEntitlement(Product $product, ?User $user = null, ?string $email = null): Order
    {
        $email = $email ?: $user?->email ?: 'buyer@example.com';

        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'order_number' => 'LBX-'.Str::upper(Str::random(10)),
            'user_id' => $user?->id,
            'customer_name' => $user?->name ?: 'Guest Buyer',
            'customer_email' => $email,
            'order_status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_minor' => $product->regular_price_minor,
            'discount_minor' => 0,
            'total_minor' => $product->regular_price_minor,
            'currency' => 'BDT',
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'purchasable_type' => Product::class,
            'purchasable_id' => $product->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'quantity' => 1,
            'unit_price_minor' => $product->regular_price_minor,
            'discount_minor' => 0,
            'total_minor' => $product->regular_price_minor,
            'currency' => 'BDT',
            'snapshot' => [],
        ]);

        Entitlement::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'product_id' => $product->id,
            'customer_email' => $email,
            'status' => 'active',
            'granted_at' => now(),
        ]);

        return $order;
    }
}
