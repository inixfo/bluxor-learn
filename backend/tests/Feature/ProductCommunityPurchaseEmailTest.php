<?php

namespace Tests\Feature;

use App\Jobs\SendPurchaseConfirmationEmail;
use App\Mail\PurchaseConfirmationMail;
use App\Models\Bundle;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\GuestAccessService;
use App\Services\PaymentCompletionService;
use App\Services\ProductCommunityAccessService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProductCommunityPurchaseEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $compiled = sys_get_temp_dir().DIRECTORY_SEPARATOR.'learn_bluxor_views_'.uniqid();
        File::ensureDirectoryExists($compiled);
        config(['view.compiled' => $compiled]);
    }

    public function test_admin_can_enable_disable_and_validate_product_community(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();

        $this->actingAs($admin)->patchJson('/api/v1/admin/products/'.$product->id, [
            'community_enabled' => true,
            'community_name' => 'N8N Automation Lab Community',
            'community_url' => 'not-a-url',
        ])->assertUnprocessable()->assertJsonValidationErrors('community_url');

        $this->actingAs($admin)->patchJson('/api/v1/admin/products/'.$product->id, [
            'community_enabled' => true,
            'community_name' => 'N8N Automation Lab Community',
            'community_url' => 'https://www.facebook.com/groups/n8n-lab',
        ])->assertOk()
            ->assertJsonPath('data.community_enabled', true)
            ->assertJsonPath('data.community_name', 'N8N Automation Lab Community')
            ->assertJsonPath('data.community_url', 'https://www.facebook.com/groups/n8n-lab');

        $this->actingAs($admin)->patchJson('/api/v1/admin/products/'.$product->id, [
            'community_enabled' => false,
        ])->assertOk()
            ->assertJsonPath('data.community_enabled', false)
            ->assertJsonPath('data.community_name', null)
            ->assertJsonPath('data.community_url', null);
    }

    public function test_community_url_is_not_exposed_publicly_but_paid_buyers_can_access_it(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();

        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();
        $this->enableCommunity($product);

        $publicProduct = $this->getJson('/api/v1/catalog/'.$product->slug)->assertOk();
        $this->assertStringNotContainsString('community_url', $publicProduct->getContent());
        $this->assertStringNotContainsString('https://www.facebook.com/groups/n8n-lab', $publicProduct->getContent());

        $guest = $this->guestOrderForProduct($product);
        $this->getJson('/api/v1/guest/orders/'.$guest['order']->order_number.'?guest_access_token='.$guest['token'])->assertForbidden();

        $this->markPaid($guest['order']);
        $this->getJson('/api/v1/guest/orders/'.$guest['order']->order_number.'?guest_access_token='.$guest['token'])
            ->assertOk()
            ->assertJsonPath('data.communities.0.name', 'N8N Automation Lab Community')
            ->assertJsonPath('data.communities.0.url', 'https://www.facebook.com/groups/n8n-lab');

        $customer = User::where('email', 'rakib@example.com')->firstOrFail();
        $registered = $this->registeredOrderForProduct($product, $customer);
        $this->markPaid($registered);

        $this->actingAs($customer)->getJson('/api/v1/account/library/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.communities.0.url', 'https://www.facebook.com/groups/n8n-lab');

        $other = User::factory()->create(['email' => 'other-community@example.com']);
        $this->actingAs($other)->getJson('/api/v1/account/library/'.$product->id)->assertForbidden();
    }

    public function test_bundle_communities_are_deduplicated_and_multiple_different_communities_work(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();

        $bundle = Bundle::where('slug', 'complete-learning-bundle')->firstOrFail();
        $products = $bundle->products()->where('products.status', 'published')->take(3)->get();
        $this->assertGreaterThanOrEqual(2, $products->count());

        $this->enableCommunity($products[0], 'Shared Community', 'https://www.facebook.com/groups/shared');
        $this->enableCommunity($products[1], 'Shared Community Duplicate', 'https://www.facebook.com/groups/shared/');
        if ($products->get(2)) {
            $this->enableCommunity($products[2], 'Second Community', 'https://www.facebook.com/groups/second');
        }

        $customer = User::where('email', 'rakib@example.com')->firstOrFail();
        $orderNumber = $this->actingAs($customer)->postJson('/api/v1/checkout/orders', [
            'bundle_id' => $bundle->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'payment_method' => 'card',
        ])->assertCreated()->json('data.order.order_number');
        $order = Order::where('order_number', $orderNumber)->firstOrFail();
        $this->markPaid($order);

        $payload = $this->actingAs($customer)->getJson('/api/v1/account/orders/'.$order->order_number)
            ->assertOk()
            ->json('data.communities');

        $urls = collect($payload)->pluck('url')->all();
        $this->assertSame(1, collect($urls)->filter(fn ($url) => rtrim($url, '/') === 'https://www.facebook.com/groups/shared')->count());
        $this->assertContains('https://www.facebook.com/groups/second', $urls);
    }

    public function test_existing_products_without_community_continue_working(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();

        $customer = User::where('email', 'rakib@example.com')->firstOrFail();
        $product = Product::where('slug', 'practical-bug-bounty')->firstOrFail();
        $order = $this->registeredOrderForProduct($product, $customer);
        $this->markPaid($order);

        $this->actingAs($customer)->getJson('/api/v1/account/library/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.communities', []);
    }

    public function test_purchase_email_is_branded_secure_and_includes_community_once(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['app.url' => 'https://learn.bluxor.com', 'learn.admin_timezone' => 'Asia/Dhaka']);
        Queue::fake();
        Mail::fake();

        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();
        $this->enableCommunity($product);
        $guest = $this->guestOrderForProduct($product);
        $this->markPaid($guest['order']);

        (new SendPurchaseConfirmationEmail($guest['order']->id))->handle(
            app(GuestAccessService::class),
            app(ProductCommunityAccessService::class)
        );

        Mail::assertSent(PurchaseConfirmationMail::class, function (PurchaseConfirmationMail $mail) use ($guest) {
            $html = $mail->render();
            $text = view('emails.purchase-confirmation-text', $mail->templateData())->render();

            $this->assertSame('Your purchase is ready - '.$guest['order']->order_number, $mail->build()->subject);
            $this->assertStringContainsString('Learn by Bluxor', $html);
            $this->assertStringContainsString('Purchase Confirmed', $html);
            $this->assertStringContainsString('Access Your Purchase', $html);
            $this->assertStringContainsString('guest_access_token=', $mail->accessUrl);
            $this->assertStringContainsString('N8N Automation Lab Community', $html);
            $this->assertSame(1, substr_count($html, 'https://www.facebook.com/groups/n8n-lab'));
            $this->assertStringContainsString($guest['order']->order_number, $html);
            $this->assertStringContainsString('BDT', $html);
            $this->assertStringContainsString('AI Automation with n8n', $html);
            $this->assertStringContainsString('Order Details', $html);
            $this->assertStringContainsString('support@bluxor.com', $html);
            $this->assertStringContainsString('Your purchase is confirmed. Access your products and community.', $html);
            $this->assertStringNotContainsString('utm_campaign', $html);
            $this->assertStringContainsString('Access your purchase:', $text);
            $this->assertStringContainsString('Community access:', $text);

            return true;
        });
    }

    public function test_registered_purchase_email_uses_account_url_and_omits_community_when_absent(): void
    {
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        Queue::fake();

        $customer = User::where('email', 'rakib@example.com')->firstOrFail();
        $product = Product::where('slug', 'practical-bug-bounty')->firstOrFail();
        $order = $this->registeredOrderForProduct($product, $customer);
        $this->markPaid($order);

        (new SendPurchaseConfirmationEmail($order->id))->handle(
            app(GuestAccessService::class),
            app(ProductCommunityAccessService::class)
        );

        Mail::assertSent(PurchaseConfirmationMail::class, function (PurchaseConfirmationMail $mail) use ($order) {
            $html = $mail->render();

            $this->assertStringContainsString('/account/orders/'.$order->order_number, $mail->accessUrl);
            $this->assertStringNotContainsString('Join Your Community', $html);
            $this->assertStringContainsString('Your purchase is confirmed and your digital products are ready.', $html);

            return true;
        });
    }

    public function test_pending_or_failed_orders_do_not_send_purchase_confirmation(): void
    {
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        Queue::fake();

        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();
        $guest = $this->guestOrderForProduct($product);

        (new SendPurchaseConfirmationEmail($guest['order']->id))->handle(
            app(GuestAccessService::class),
            app(ProductCommunityAccessService::class)
        );

        $guest['order']->forceFill(['payment_status' => 'failed'])->save();
        (new SendPurchaseConfirmationEmail($guest['order']->id))->handle(
            app(GuestAccessService::class),
            app(ProductCommunityAccessService::class)
        );

        Mail::assertNothingSent();
    }

    public function test_email_failure_does_not_undo_successful_payment_or_meta_and_entitlements(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();
        $guest = $this->guestOrderForProduct($product);
        $paid = $this->markPaid($guest['order']);

        try {
            (new SendPurchaseConfirmationEmail($paid->id))->handle(
                app(GuestAccessService::class),
                app(ProductCommunityAccessService::class)
            );
            $this->fail('Expected mail transport failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('smtp down', $exception->getMessage());
        }

        $this->assertSame('paid', $paid->payment_status);
        $this->assertDatabaseHas('entitlements', ['order_id' => $paid->id, 'product_id' => $product->id, 'status' => 'active']);
        $this->assertDatabaseHas('meta_conversion_events', ['order_id' => $paid->id, 'event_name' => 'Purchase']);
        Queue::assertPushed(SendPurchaseConfirmationEmail::class);
    }

    private function enableCommunity(Product $product, string $name = 'N8N Automation Lab Community', string $url = 'https://www.facebook.com/groups/n8n-lab'): void
    {
        $product->forceFill([
            'community_enabled' => true,
            'community_name' => $name,
            'community_url' => $url,
        ])->save();
    }

    private function guestOrderForProduct(Product $product): array
    {
        $payload = $this->postJson('/api/v1/checkout/orders', [
            'product_id' => $product->id,
            'customer_name' => 'Guest Buyer',
            'customer_email' => 'community-guest@example.com',
            'payment_method' => 'card',
        ])->assertCreated()->json('data');

        return [
            'order' => Order::where('order_number', $payload['order']['order_number'])->firstOrFail(),
            'token' => $payload['guest_access_token'],
        ];
    }

    private function registeredOrderForProduct(Product $product, User $customer): Order
    {
        $orderNumber = $this->actingAs($customer)->postJson('/api/v1/checkout/orders', [
            'product_id' => $product->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'payment_method' => 'card',
        ])->assertCreated()->json('data.order.order_number');

        return Order::where('order_number', $orderNumber)->firstOrFail();
    }

    private function markPaid(Order $order): Order
    {
        return app(PaymentCompletionService::class)->markPaid($order, 'test', 'test-paid-'.$order->id, [
            'provider_transaction_id' => 'test-'.$order->id,
            'amount_minor' => $order->total_minor,
            'currency' => $order->currency,
            'state' => 'paid',
        ]);
    }
}
