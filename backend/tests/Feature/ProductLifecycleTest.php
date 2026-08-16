<?php

namespace Tests\Feature;

use App\Models\Entitlement;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Resource;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_archive_and_restore_product_and_public_checkout_is_blocked(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $customer = User::where('email', 'rakib@example.com')->firstOrFail();
        $product = Product::where('slug', 'cybersecurity-essentials')->firstOrFail();

        $this->actingAs($customer)
            ->postJson('/api/v1/admin/products/'.$product->id.'/archive')
            ->assertForbidden();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/products/'.$product->id.'/archive')
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'archived', 'deleted_at' => null]);

        $this->getJson('/api/v1/products?q=Cybersecurity%20Essentials')
            ->assertOk()
            ->assertJsonMissing(['slug' => 'cybersecurity-essentials']);

        $this->getJson('/api/v1/products?category=security')
            ->assertOk()
            ->assertJsonMissing(['slug' => 'cybersecurity-essentials']);

        $this->getJson('/api/v1/search/products?q=Cybersecurity')
            ->assertOk()
            ->assertJsonMissing(['slug' => 'cybersecurity-essentials']);

        $this->getJson('/api/v1/catalog/cybersecurity-essentials')->assertNotFound();

        $this->postJson('/api/v1/checkout/quote', ['product_id' => $product->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/products?status=archived')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'cybersecurity-essentials']);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/products/'.$product->id.'/restore')
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'draft']);
    }

    public function test_admin_can_soft_delete_restore_and_customer_history_survives(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $customer = User::where('email', 'rakib@example.com')->firstOrFail();
        $product = Product::where('slug', 'cybersecurity-essentials')->firstOrFail();
        $order = $this->createPaidOrderWithEntitlement($product, $customer);

        $this->actingAs($customer)
            ->deleteJson('/api/v1/admin/products/'.$product->id)
            ->assertForbidden();

        $this->actingAs($admin)
            ->deleteJson('/api/v1/admin/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.ok', true);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('payment_transactions', ['order_id' => $order->id, 'status' => 'paid']);
        $this->assertDatabaseHas('entitlements', ['order_id' => $order->id, 'product_id' => $product->id, 'status' => 'active']);

        $this->getJson('/api/v1/catalog/cybersecurity-essentials')->assertNotFound();
        $this->postJson('/api/v1/checkout/quote', ['product_id' => $product->id])->assertUnprocessable();

        $this->actingAs($customer)
            ->getJson('/api/v1/account/orders/'.$order->order_number)
            ->assertOk()
            ->assertJsonPath('data.items.0.name', $product->name);

        $this->actingAs($customer)
            ->getJson('/api/v1/account/library/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.title', $product->name);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/products?status=deleted')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'cybersecurity-essentials']);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/products/'.$product->id.'/restore-deleted')
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->assertFalse(Product::withTrashed()->findOrFail($product->id)->trashed());
    }

    public function test_product_delete_dependency_safety_for_bundles_landing_pages_and_resources(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $bundledProduct = Product::where('slug', 'ai-automation-n8n')->firstOrFail();

        $this->actingAs($admin)
            ->deleteJson('/api/v1/admin/products/'.$bundledProduct->id)
            ->assertUnprocessable()
            ->assertSee('active bundle');

        $product = Product::where('slug', 'cybersecurity-essentials')->firstOrFail();
        $landingPage = \App\Models\LandingPage::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Cyber Essentials Landing',
            'slug' => 'cyber-essentials-landing',
            'status' => 'published',
            'primary_product_id' => $product->id,
        ]);
        $version = $landingPage->versions()->create([
            'uuid' => (string) Str::uuid(),
            'version_number' => 1,
            'package_path' => 'landing-pages/cyber-essentials/v1.zip',
            'manifest' => ['schemaVersion' => 2, 'sdkVersion' => '2', 'name' => 'Cyber Essentials Landing', 'entry' => 'dist/index.html'],
            'entry_path' => 'dist/index.html',
            'checksum' => hash('sha256', 'cyber-essentials'),
            'sdk_version' => '2',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $landingPage->forceFill(['published_version_id' => $version->id])->save();
        $landingPage->offers()->create([
            'offer_key' => 'single',
            'offer_type' => 'product',
            'product_id' => $product->id,
            'landing_page_version_id' => $version->id,
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        $resource = Resource::create([
            'title' => 'Shared Security Checklist',
            'slug' => 'shared-security-checklist',
            'resource_type' => 'PDF',
            'source_type' => 'external_url',
            'external_url' => 'https://example.com/checklist.pdf',
            'access_type' => 'purchase_required',
            'status' => 'published',
            'version' => '1.0',
        ]);
        $resource->products()->attach($product->id);

        $this->actingAs($admin)
            ->deleteJson('/api/v1/admin/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.active_landing_pages', 1);

        $this->postJson('/api/v1/checkout/quote', [
            'landing_page_slug' => 'cyber-essentials-landing',
            'offer_key' => 'single',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('offer_key');

        $this->assertDatabaseHas('resources', ['id' => $resource->id, 'slug' => 'shared-security-checklist']);
        $this->assertDatabaseHas('product_resource', ['product_id' => $product->id, 'resource_id' => $resource->id]);
    }

    private function createPaidOrderWithEntitlement(Product $product, User $customer): Order
    {
        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'order_number' => 'LBLX-2026-HISTORY',
            'user_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'order_status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_minor' => $product->sale_price_minor ?? $product->regular_price_minor,
            'discount_minor' => 0,
            'total_minor' => $product->sale_price_minor ?? $product->regular_price_minor,
            'currency' => $product->currency,
        ]);

        $item = $order->items()->create([
            'purchasable_type' => 'product',
            'purchasable_id' => $product->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'unit_price_minor' => $product->sale_price_minor ?? $product->regular_price_minor,
            'total_minor' => $product->sale_price_minor ?? $product->regular_price_minor,
            'currency' => $product->currency,
            'snapshot' => ['title' => $product->name],
        ]);

        Entitlement::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'product_id' => $product->id,
            'customer_email' => $customer->email,
            'status' => 'active',
            'granted_at' => now(),
        ]);

        PaymentTransaction::create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'gateway' => 'manual-test',
            'provider_transaction_id' => 'txn-history',
            'amount_minor' => $order->total_minor,
            'currency' => $order->currency,
            'status' => 'paid',
            'normalized_state' => 'paid',
            'paid_at' => now(),
        ]);

        return $order;
    }
}
