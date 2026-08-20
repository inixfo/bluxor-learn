<?php

namespace Tests\Feature;

use App\Jobs\SendPurchaseConfirmationEmail;
use App\Models\AuditLog;
use App\Models\Entitlement;
use App\Models\MetaConversionEvent;
use App\Models\Order;
use App\Models\PaymentEvent;
use App\Models\Product;
use App\Models\Resource;
use App\Models\ResourceAccessGrant;
use App\Models\User;
use App\Services\PaymentCompletionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualPaymentAndAccessGrantTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_pending_payment_through_normal_completion_once(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $order = $this->pendingGuestOrder('manual-paid@example.com');

        $this->actingAs($admin)->postJson('/api/v1/admin/orders/'.$order->id.'/approve-payment', [
            'confirmation' => true,
            'amount_minor' => $order->total_minor,
            'currency' => $order->currency,
            'payment_method' => 'bkash',
            'reference' => 'TXN123',
            'reason' => 'Payment confirmed manually in merchant account.',
        ])->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.manual_payment_approval.payment_method', 'bkash');

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('completed', $order->fresh()->order_status);
        $this->assertSame(1, Entitlement::where('order_id', $order->id)->count());
        $this->assertSame(1, PaymentEvent::where('gateway', 'admin_manual')->count());
        $this->assertSame(1, MetaConversionEvent::where('event_name', 'Purchase')->count());
        $this->assertSame(1, DB::table('analytics_events')->where('order_id', $order->id)->where('event_name', 'purchase')->count());
        $this->assertSame(1, AuditLog::where('action', 'order.payment_manually_approved')->count());
        Queue::assertPushed(SendPurchaseConfirmationEmail::class, 1);

        $this->actingAs($admin)->postJson('/api/v1/admin/orders/'.$order->id.'/approve-payment', [
            'confirmation' => true,
            'amount_minor' => $order->total_minor,
            'currency' => $order->currency,
            'payment_method' => 'bkash',
            'reference' => 'TXN123',
            'reason' => 'Payment confirmed manually in merchant account.',
        ])->assertOk();

        $this->assertSame(1, Entitlement::where('order_id', $order->id)->count());
        $this->assertSame(1, PaymentEvent::where('gateway', 'admin_manual')->count());
        $this->assertSame(1, MetaConversionEvent::where('event_name', 'Purchase')->count());
        Queue::assertPushed(SendPurchaseConfirmationEmail::class, 1);
    }

    public function test_manual_payment_approval_validation_and_authorization(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $customer = User::where('email', 'rakib@example.com')->firstOrFail();
        $order = $this->pendingGuestOrder('manual-validation@example.com');

        $payload = [
            'confirmation' => true,
            'amount_minor' => $order->total_minor,
            'currency' => $order->currency,
            'payment_method' => 'cash',
            'reason' => 'Cash received at office.',
        ];

        $this->actingAs($customer)->postJson('/api/v1/admin/orders/'.$order->id.'/approve-payment', $payload)->assertForbidden();
        $this->actingAs($admin)->postJson('/api/v1/admin/orders/'.$order->id.'/approve-payment', array_merge($payload, ['amount_minor' => $order->total_minor + 1]))->assertStatus(422);
        $this->actingAs($admin)->postJson('/api/v1/admin/orders/'.$order->id.'/approve-payment', array_merge($payload, ['currency' => 'USD']))->assertStatus(422);

        $order->forceFill(['payment_status' => 'refunded'])->save();
        $this->actingAs($admin)->postJson('/api/v1/admin/orders/'.$order->id.'/approve-payment', $payload)->assertStatus(422);
    }

    public function test_later_piprapay_success_after_manual_approval_does_not_duplicate_side_effects(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $order = $this->pendingGuestOrder('manual-then-pipra@example.com');

        $this->actingAs($admin)->postJson('/api/v1/admin/orders/'.$order->id.'/approve-payment', [
            'confirmation' => true,
            'amount_minor' => $order->total_minor,
            'currency' => $order->currency,
            'payment_method' => 'piprapay_manual',
            'reference' => 'PP-MANUAL',
            'reason' => 'PipraPay dashboard confirmed full payment.',
        ])->assertOk();

        app(PaymentCompletionService::class)->markPaid($order->fresh(), 'piprapay', 'webhook:late-success', [
            'provider_transaction_id' => 'late-success',
            'amount_minor' => $order->total_minor,
            'currency' => $order->currency,
        ]);

        $this->assertSame(1, Entitlement::where('order_id', $order->id)->count());
        $this->assertSame(1, MetaConversionEvent::where('event_name', 'Purchase')->count());
        $this->assertSame(1, DB::table('analytics_events')->where('order_id', $order->id)->where('event_name', 'purchase')->count());
        $this->assertSame(1, $order->paymentTransactions()->where('gateway', 'admin_manual')->count());
        $this->assertSame(1, $order->paymentTransactions()->where('gateway', 'piprapay')->count());
        Queue::assertPushed(SendPurchaseConfirmationEmail::class, 1);
    }

    public function test_admin_product_grant_enables_library_download_resource_and_community_without_revenue_events(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->useTempPrivateDisk();
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $customer = User::factory()->create(['email' => 'manual-product@example.com', 'email_verified_at' => now()]);
        $product = Product::where('slug', 'practical-bug-bounty')->firstOrFail();
        $product->forceFill(['community_enabled' => true, 'community_name' => 'Bug Bounty Lab', 'community_url' => 'https://facebook.com/groups/bug-bounty-lab'])->save();
        $file = $product->files()->firstOrFail();
        Storage::disk('private')->put($file->storage_path, 'manual-file');
        $resource = $this->uploadedResource('manual-product-resource', 'purchase_required', [$product->id]);
        Storage::disk('private')->put($resource->storage_path, '{"ok":true}');
        $ordersBefore = Order::count();
        $paymentsBefore = PaymentEvent::count();
        $metaBefore = MetaConversionEvent::where('event_name', 'Purchase')->count();

        $this->actingAs($admin)->postJson('/api/v1/admin/customers/'.$customer->id.'/access/products', [
            'product_id' => $product->id,
            'reason' => 'Support goodwill access.',
        ])->assertCreated()->assertJsonPath('data.status', 'granted');

        $this->assertSame($ordersBefore, Order::count());
        $this->assertSame($paymentsBefore, PaymentEvent::count());
        $this->assertSame($metaBefore, MetaConversionEvent::where('event_name', 'Purchase')->count());
        $this->actingAs($customer)->getJson('/api/v1/account/library')->assertOk()->assertJsonFragment(['product_id' => $product->id]);
        $this->actingAs($customer)->postJson('/api/v1/account/downloads/'.$file->id)->assertOk();
        $this->actingAs($customer)->get('/api/v1/resources/'.$resource->slug.'/download')->assertOk();
        $this->actingAs($customer)->getJson('/api/v1/account/library/'.$product->id)->assertJsonPath('data.communities.0.name', 'Bug Bounty Lab');
        $this->assertSame(1, AuditLog::where('action', 'access.product_granted')->count());
    }

    public function test_manual_product_grant_expiration_revocation_and_purchase_dedupe(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $customer = User::factory()->create(['email' => 'manual-expire@example.com', 'email_verified_at' => now()]);
        $product = Product::where('slug', 'cybersecurity-essentials')->firstOrFail();

        $this->actingAs($admin)->postJson('/api/v1/admin/customers/'.$customer->id.'/access/products', [
            'product_id' => $product->id,
            'expires_at' => now()->addDay()->toISOString(),
            'reason' => 'Temporary access.',
        ])->assertCreated();
        $entitlement = Entitlement::where('user_id', $customer->id)->where('product_id', $product->id)->firstOrFail();
        $this->actingAs($admin)->postJson('/api/v1/admin/access/product-entitlements/'.$entitlement->id.'/revoke', [
            'reason' => 'Temporary access complete.',
        ])->assertOk()->assertJsonPath('data.status', 'revoked');
        $this->actingAs($customer)->getJson('/api/v1/account/library')->assertOk()->assertJsonMissing(['product_id' => $product->id]);

        $this->actingAs($admin)->postJson('/api/v1/admin/customers/'.$customer->id.'/access/products', [
            'product_id' => $product->id,
            'reason' => 'Restore access.',
        ])->assertCreated();
        $order = $this->pendingAccountOrder($customer, $product);
        app(PaymentCompletionService::class)->markPaid($order, 'test', 'manual-dedupe-purchase', [
            'provider_transaction_id' => 'manual-dedupe-purchase',
            'amount_minor' => $order->total_minor,
            'currency' => $order->currency,
        ]);

        $library = $this->actingAs($customer)->getJson('/api/v1/account/library')->assertOk()->json('data');
        $this->assertSame(1, collect($library)->where('product_id', $product->id)->count());
        $downloads = $this->actingAs($customer)->getJson('/api/v1/account/downloads')->assertOk()->json('data');
        $this->assertSame($product->files()->where('status', 'active')->count(), collect($downloads)->where('product_id', $product->id)->count());

        $purchase = Entitlement::where('order_id', $order->id)->firstOrFail();
        $this->actingAs($admin)->postJson('/api/v1/admin/access/product-entitlements/'.$purchase->id.'/revoke', [
            'reason' => 'Should not revoke purchase.',
        ])->assertStatus(422);
    }

    public function test_direct_resource_grant_unlocks_only_that_resource_and_can_be_revoked(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->useTempPrivateDisk();
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $customer = User::factory()->create(['email' => 'manual-resource@example.com', 'email_verified_at' => now()]);
        $first = $this->uploadedResource('direct-resource-one', 'purchase_required');
        $second = $this->uploadedResource('direct-resource-two', 'purchase_required');
        Storage::disk('private')->put($first->storage_path, 'first');
        Storage::disk('private')->put($second->storage_path, 'second');
        $ordersBefore = Order::count();

        $this->actingAs($admin)->postJson('/api/v1/admin/customers/'.$customer->id.'/access/resources', [
            'resource_id' => $first->id,
            'reason' => 'One-off worksheet access.',
        ])->assertCreated();

        $this->assertSame($ordersBefore, Order::count());
        $this->assertSame(0, MetaConversionEvent::count());
        $this->actingAs($customer)->getJson('/api/v1/account/resources')->assertOk()->assertJsonFragment(['resource_id' => $first->id]);
        $this->actingAs($customer)->get('/api/v1/resources/'.$first->slug.'/download')->assertOk();
        $this->actingAs($customer)->get('/api/v1/resources/'.$second->slug.'/download')->assertForbidden();
        $this->actingAs($customer)->getJson('/api/v1/account/library')->assertJsonCount(0, 'data');

        $grant = ResourceAccessGrant::where('user_id', $customer->id)->where('resource_id', $first->id)->firstOrFail();
        $this->actingAs($admin)->postJson('/api/v1/admin/access/resource-grants/'.$grant->id.'/revoke', [
            'reason' => 'Resource no longer needed.',
        ])->assertOk()->assertJsonPath('data.status', 'revoked');
        $this->actingAs($customer)->get('/api/v1/resources/'.$first->slug.'/download')->assertForbidden();
    }

    private function pendingGuestOrder(string $email): Order
    {
        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();
        $payload = $this->postJson('/api/v1/checkout/orders', [
            'product_id' => $product->id,
            'customer_name' => 'Manual Buyer',
            'customer_email' => $email,
            'payment_method' => 'card',
        ])->assertCreated()->json('data.order');

        return Order::where('order_number', $payload['order_number'])->firstOrFail();
    }

    private function pendingAccountOrder(User $customer, Product $product): Order
    {
        $payload = $this->actingAs($customer)->postJson('/api/v1/checkout/orders', [
            'product_id' => $product->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'payment_method' => 'card',
        ])->assertCreated()->json('data.order');

        return Order::where('order_number', $payload['order_number'])->firstOrFail();
    }

    private function uploadedResource(string $slug, string $accessType, array $productIds = []): Resource
    {
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $payload = $this->actingAs($admin)->post('/api/v1/admin/resources', [
            'title' => str($slug)->replace('-', ' ')->title()->toString(),
            'slug' => $slug,
            'description' => 'Manual access resource.',
            'resource_type' => 'n8n Workflow',
            'source_type' => 'uploaded_file',
            'access_type' => $accessType,
            'status' => 'published',
            'version' => '1.0',
            'product_ids' => $productIds,
            'file' => UploadedFile::fake()->create($slug.'.txt', 2, 'text/plain'),
        ])->assertCreated()->json('data');

        return Resource::findOrFail($payload['id']);
    }

    private function useTempPrivateDisk(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'learn_bluxor_manual_access_test_'.uniqid();
        File::ensureDirectoryExists($root);
        config(['filesystems.disks.private.root' => $root]);
    }
}
