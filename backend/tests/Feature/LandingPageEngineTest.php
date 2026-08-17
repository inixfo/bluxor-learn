<?php

namespace Tests\Feature;

use App\Models\LandingPage;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class LandingPageEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_publish_serve_context_and_checkout_landing_offer(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->useTempStorage();
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();

        $versionId = $this->actingAs($admin)->post('/api/v1/admin/landing-pages/uploads', [
            'package' => $this->zipUpload('n8n-funnel.zip', extra: [
                'dist/assets/images/logo.svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>',
                'dist/assets/fonts/demo.woff2' => 'woff2',
            ]),
            'name' => 'N8N Freelancer Funnel',
            'slug' => 'n8n-freelancer',
            'primary_product_id' => $product->id,
            'offers' => [
                ['offer_key' => 'single', 'offer_type' => 'product', 'product_id' => $product->id, 'is_primary' => true],
            ],
        ])->assertCreated()->assertJsonPath('data.version_number', 1)->json('data.id');

        $page = LandingPage::where('slug', 'n8n-freelancer')->firstOrFail();
        $this->actingAs($admin)->postJson('/api/v1/admin/landing-pages/'.$page->id.'/versions/'.$versionId.'/publish')->assertOk();

        $response = $this->get('/go/n8n-freelancer')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('<base href="/go/n8n-freelancer/">', false)
            ->assertSee('/landing-runtime/lbx-runtime.v2.js', false)
            ->assertSee('id="lbx-context"', false);
        $html = $response->getContent();
        $csp = $response->headers->get('Content-Security-Policy') ?: '';
        $this->assertStringContainsString("script-src 'self' https://connect.facebook.net", $csp);
        $this->assertStringContainsString("connect-src 'self' https://www.facebook.com https://connect.facebook.net", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);

        $this->assertLessThan(
            strpos($html, 'href="assets/styles.css"'),
            strpos($html, '<base href="/go/n8n-freelancer/">')
        );

        $this->get('/go/n8n-freelancer/assets/styles.css')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=utf-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->get('/go/n8n-freelancer/assets/images/logo.svg')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->get('/go/n8n-freelancer/assets/fonts/demo.woff2')
            ->assertOk()
            ->assertHeader('Content-Type', 'font/woff2')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->get('/go/n8n-freelancer/assets/missing.css')->assertNotFound();
        $this->get('/go/n8n-freelancer/../source.zip')->assertNotFound();

        $this->get('/lp/n8n-freelancer')->assertRedirect('/go/n8n-freelancer');
        $context = $this->getJson('/api/v1/landing-pages/n8n-freelancer/context')
            ->assertOk()
            ->assertJsonPath('data.product.content_id', 'product:'.$product->id)
            ->assertJsonPath('data.offers.single.content_id', 'product:'.$product->id)
            ->assertJsonPath('data.offers.single.name', 'AI Automation with n8n')
            ->json('data');

        $this->assertArrayNotHasKey('storage_path', $context['product']);

        $this->postJson('/api/v1/checkout/quote', [
            'landing_page_slug' => 'n8n-freelancer',
            'offer_key' => 'single',
            'total_minor' => 1,
        ])->assertOk()->assertJsonPath('data.total_minor', 99000);

        $previewUrl = $this->actingAs($admin)->getJson('/api/v1/admin/landing-page-versions/'.$versionId.'/preview-url')
            ->assertOk()
            ->json('data.url');

        $previewHtml = $this->get($previewUrl)
            ->assertOk()
            ->assertSee('<base href="/landing-preview/'.$versionId.'/">', false)
            ->getContent();

        $this->assertLessThan(
            strpos($previewHtml, 'href="assets/styles.css"'),
            strpos($previewHtml, '<base href="/landing-preview/'.$versionId.'/">')
        );

        $this->get('/landing-preview/'.$versionId.'/assets/styles.css')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=utf-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_package_upload_creates_immutable_versions_and_restore_changes_pointer(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->useTempStorage();
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();

        $v1 = $this->actingAs($admin)->post('/api/v1/admin/landing-pages/uploads', [
            'package' => $this->zipUpload('v1.zip', 'Version 1'),
            'slug' => 'versioned-page',
            'primary_product_id' => $product->id,
        ])->assertCreated()->json('data.id');

        $v2 = $this->actingAs($admin)->post('/api/v1/admin/landing-pages/uploads', [
            'package' => $this->zipUpload('v2.zip', 'Version 2'),
            'slug' => 'versioned-page',
            'primary_product_id' => $product->id,
        ])->assertCreated()->assertJsonPath('data.version_number', 2)->json('data.id');

        $page = LandingPage::where('slug', 'versioned-page')->firstOrFail();
        $this->assertSame(2, $page->versions()->count());

        $this->actingAs($admin)->postJson('/api/v1/admin/landing-pages/'.$page->id.'/versions/'.$v2.'/publish')->assertOk();
        $this->assertSame((int) $v2, (int) $page->fresh()->published_version_id);

        $this->actingAs($admin)->postJson('/api/v1/admin/landing-pages/'.$page->id.'/versions/'.$v1.'/publish')->assertOk();
        $this->assertSame((int) $v1, (int) $page->fresh()->published_version_id);
    }

    public function test_malicious_package_is_rejected_and_customer_cannot_upload(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->useTempStorage();
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $customer = User::where('email', 'rakib@example.com')->firstOrFail();

        $this->actingAs($customer)->post('/api/v1/admin/landing-pages/uploads', [
            'package' => $this->zipUpload('bad.zip', 'bad', ['../../config.php' => '<?php echo "bad";']),
        ])->assertForbidden();

        $this->actingAs($admin)->withHeaders(['Accept' => 'application/json'])->post('/api/v1/admin/landing-pages/uploads', [
            'package' => $this->zipUpload('bad.zip', 'bad', ['dist/shell.php' => '<?php echo "bad";']),
        ])->assertStatus(422)->assertJsonPath('errors.package.0', '`dist/shell.php` unsupported file type `.php`.');
    }

    public function test_v2_package_rejects_uploaded_javascript_and_inline_execution_vectors(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->useTempStorage();
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();

        foreach ([
            ['dist/assets/app.js' => 'alert(1)'],
            ['dist/index.html' => '<!doctype html><html><body><script>alert(1)</script></body></html>'],
            ['dist/index.html' => '<!doctype html><html><body><script src="https://evil.test/x.js"></script></body></html>'],
            ['dist/index.html' => '<!doctype html><html><body><button onclick="alert(1)">Buy</button></body></html>'],
            ['dist/index.html' => '<!doctype html><html><body><img src="x" onerror="alert(1)"></body></html>'],
            ['dist/index.html' => '<!doctype html><html><body><a href="javascript:alert(1)">Bad</a></body></html>'],
            ['dist/index.html' => '<!doctype html><html><head><base href="https://evil.test/"></head><body>Bad</body></html>'],
        ] as $extra) {
            $this->actingAs($admin)->withHeaders(['Accept' => 'application/json'])->post('/api/v1/admin/landing-pages/uploads', [
                'package' => $this->zipUpload('unsafe.zip', 'bad', $extra),
                'slug' => 'unsafe-'.uniqid(),
            ])->assertStatus(422);
        }
    }

    public function test_v2_package_accepts_safe_html_css_image_and_rejects_invalid_slug(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->useTempStorage();
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();

        $this->actingAs($admin)->post('/api/v1/admin/landing-pages/uploads', [
            'package' => $this->zipUpload('safe.zip', 'Safe', [
                'dist/assets/styles.css' => '.hero{animation:fade 1s ease}.hero{background:url("images/cover.png")}',
                'dist/assets/images/cover.png' => 'png',
            ]),
            'slug' => 'safe-v2',
            'primary_product_id' => $product->id,
        ])->assertCreated();

        $this->actingAs($admin)->withHeaders(['Accept' => 'application/json'])->post('/api/v1/admin/landing-pages/uploads', [
            'package' => $this->zipUpload('safe.zip'),
            'slug' => '../admin',
            'primary_product_id' => $product->id,
        ])->assertStatus(422);

        $this->get('/go/missing-page')->assertNotFound();
    }

    public function test_legacy_v1_package_is_not_rendered_on_native_routes(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->useTempStorage();
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();

        $versionId = $this->actingAs($admin)->post('/api/v1/admin/landing-pages/uploads', [
            'package' => $this->zipUpload('legacy-source.zip'),
            'slug' => 'legacy-v1',
            'primary_product_id' => $product->id,
        ])->assertCreated()->json('data.id');

        $page = LandingPage::where('slug', 'legacy-v1')->firstOrFail();
        $this->actingAs($admin)->postJson('/api/v1/admin/landing-pages/'.$page->id.'/versions/'.$versionId.'/publish')->assertOk();

        $version = $page->versions()->firstOrFail();
        $version->forceFill([
            'manifest' => array_merge($version->manifest, ['schemaVersion' => 1, 'sdkVersion' => '1']),
            'sdk_version' => '1',
        ])->save();

        $this->withHeaders(['Accept' => 'application/json'])->get('/go/legacy-v1')->assertNotFound();
        $this->getJson('/api/v1/landing-pages/legacy-v1/context')->assertNotFound();
    }

    public function test_associated_product_creates_default_single_offer_when_none_exists(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();
        $page = LandingPage::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Empty Offer Landing',
            'slug' => 'empty-offer-landing',
            'status' => 'draft',
        ]);

        $this->assertSame(0, $page->offers()->count());

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/landing-pages/'.$page->id.'/product', [
                'primary_product_id' => $product->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.primary_product_id', $product->id)
            ->assertJsonPath('data.offers.0.offer_key', 'single')
            ->assertJsonPath('data.offers.0.offer_type', 'product')
            ->assertJsonPath('data.offers.0.product_id', $product->id)
            ->assertJsonPath('data.offers.0.is_primary', true);

        $this->assertDatabaseHas('landing_page_offers', [
            'landing_page_id' => $page->id,
            'offer_key' => 'single',
            'offer_type' => 'product',
            'product_id' => $product->id,
            'is_primary' => true,
        ]);
    }

    public function test_associated_product_reassignment_updates_primary_offer_and_preserves_secondary_offers(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $productA = Product::where('slug', 'ai-automation-n8n')->firstOrFail();
        $productB = Product::where('slug', 'practical-bug-bounty')->firstOrFail();
        $bundle = \App\Models\Bundle::where('slug', 'complete-learning-bundle')->firstOrFail();
        $page = LandingPage::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Reassignment Landing',
            'slug' => 'reassignment-landing',
            'status' => 'draft',
            'primary_product_id' => $productA->id,
        ]);
        $page->offers()->create([
            'offer_key' => 'single',
            'offer_type' => 'product',
            'product_id' => $productA->id,
            'sort_order' => 0,
            'is_primary' => true,
        ]);
        $page->offers()->create([
            'offer_key' => 'bundle',
            'offer_type' => 'bundle',
            'bundle_id' => $bundle->id,
            'sort_order' => 1,
            'is_primary' => false,
        ]);

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/landing-pages/'.$page->id.'/product', [
                'primary_product_id' => $productB->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.primary_product_id', $productB->id);

        $page->refresh();
        $this->assertSame(2, $page->offers()->count());
        $this->assertSame(1, $page->offers()->where('is_primary', true)->count());
        $this->assertDatabaseHas('landing_page_offers', [
            'landing_page_id' => $page->id,
            'offer_key' => 'single',
            'offer_type' => 'product',
            'product_id' => $productB->id,
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('landing_page_offers', [
            'landing_page_id' => $page->id,
            'offer_key' => 'bundle',
            'offer_type' => 'bundle',
            'bundle_id' => $bundle->id,
            'is_primary' => false,
        ]);
    }

    public function test_archived_or_deleted_product_cannot_be_assigned_to_landing_page(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $archived = Product::where('slug', 'cybersecurity-essentials')->firstOrFail();
        $archived->forceFill(['status' => 'archived'])->save();
        $deleted = Product::where('slug', 'react-templates-pack')->firstOrFail();
        $deleted->delete();
        $page = LandingPage::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Availability Landing',
            'slug' => 'availability-landing',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/landing-pages/'.$page->id.'/product', [
                'primary_product_id' => $archived->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('primary_product_id');

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/landing-pages/'.$page->id.'/product', [
                'primary_product_id' => $deleted->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('primary_product_id');

        $this->assertSame(0, $page->fresh()->offers()->count());
    }

    public function test_anonymous_landing_events_capture_attribution_and_admin_reports_conversions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->useTempStorage();
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();

        $versionId = $this->actingAs($admin)->post('/api/v1/admin/landing-pages/uploads', [
            'package' => $this->zipUpload('analytics.zip'),
            'slug' => 'analytics-page',
            'primary_product_id' => $product->id,
            'offers' => [
                ['offer_key' => 'single', 'offer_type' => 'product', 'product_id' => $product->id, 'is_primary' => true],
            ],
        ])->assertCreated()->json('data.id');

        $page = LandingPage::where('slug', 'analytics-page')->firstOrFail();
        $this->actingAs($admin)->postJson('/api/v1/admin/landing-pages/'.$page->id.'/versions/'.$versionId.'/publish')->assertOk();

        $eventPayload = [
            'landing_page_id' => $page->id,
            'landing_page_version_id' => $versionId,
            'visitor_id' => 'visitor-1',
            'session_id' => 'session-1',
            'current_url' => 'https://learn.bluxor.com/go/analytics-page?utm_source=facebook&utm_medium=paid_social&utm_campaign=launch&fbclid=fb-click',
            'path' => '/go/analytics-page',
            'referrer' => 'https://facebook.com/ad',
            'utm_source' => 'facebook',
            'utm_medium' => 'paid_social',
            'utm_campaign' => 'launch',
            'fbclid' => 'fb-click',
        ];

        $this->postJson('/api/v1/analytics/events', ['event_name' => 'landing_page_view'] + $eventPayload)->assertOk();
        $this->postJson('/api/v1/analytics/events', ['event_name' => 'landing_page_view', 'event_uuid' => (string) Str::uuid()] + $eventPayload)->assertOk();
        $this->postJson('/api/v1/analytics/events', ['event_name' => 'checkout_started'] + $eventPayload)->assertOk();

        $this->assertDatabaseHas('analytics_events', [
            'landing_page_id' => $page->id,
            'source' => 'Facebook',
            'medium' => 'paid_social',
            'campaign' => 'launch',
            'fbclid' => 'fb-click',
        ]);

        $orderId = $this->postJson('/api/v1/checkout/orders', [
            'landing_page_slug' => 'analytics-page',
            'offer_key' => 'single',
            'customer_name' => 'Analytics Buyer',
            'customer_email' => 'analytics-buyer@example.com',
            'customer_phone' => '+8801711111111',
            'payment_method' => 'piprapay',
            'tracking_context' => [
                'visitor_id' => 'visitor-1',
                'session_id' => 'session-1',
                'first_touch' => $eventPayload,
                'last_touch' => $eventPayload,
            ],
        ])->assertCreated()->json('data.order.id');

        $order = Order::findOrFail($orderId);
        $order->forceFill(['payment_status' => 'paid', 'order_status' => 'completed'])->save();
        PaymentTransaction::create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'gateway' => 'piprapay',
            'amount_minor' => $order->total_minor,
            'currency' => $order->currency,
            'status' => 'paid',
            'normalized_state' => 'paid',
            'paid_at' => now()->subMinute(),
            'verified_at' => now(),
        ]);

        $this->assertSame('Facebook', $order->fresh()->metadata['order_attribution']['last_touch']['source']);

        $this->actingAs($admin)->getJson('/api/v1/admin/landing-pages/'.$page->id.'/analytics?range=30d')
            ->assertOk()
            ->assertJsonPath('data.visitors', 1)
            ->assertJsonPath('data.sessions', 1)
            ->assertJsonPath('data.page_views', 2)
            ->assertJsonPath('data.checkout_started', 1)
            ->assertJsonPath('data.orders', 1)
            ->assertJsonPath('data.paid_orders', 1)
            ->assertJsonPath('data.revenue_minor', $order->total_minor)
            ->assertJsonPath('data.source_breakdown.0.source', 'Facebook')
            ->assertJsonPath('data.source_breakdown.0.medium', 'paid_social')
            ->assertJsonPath('data.source_breakdown.0.campaign', 'launch')
            ->assertJsonPath('data.recent_conversions.0.order_number', $order->order_number);

        $orderResponse = $this->actingAs($admin)->getJson('/api/v1/admin/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.attribution.source', 'Facebook')
            ->assertJsonPath('data.attribution.campaign', 'launch');
        $this->assertNotEmpty($orderResponse->json('data.payment_completed_at'));
    }

    public function test_landing_analytics_classifies_direct_and_google_organic_sources(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->useTempStorage();
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();

        $versionId = $this->actingAs($admin)->post('/api/v1/admin/landing-pages/uploads', [
            'package' => $this->zipUpload('sources.zip'),
            'slug' => 'source-page',
            'primary_product_id' => $product->id,
        ])->assertCreated()->json('data.id');

        $page = LandingPage::where('slug', 'source-page')->firstOrFail();
        $this->actingAs($admin)->postJson('/api/v1/admin/landing-pages/'.$page->id.'/versions/'.$versionId.'/publish')->assertOk();

        $this->postJson('/api/v1/analytics/events', [
            'event_name' => 'landing_page_view',
            'landing_page_id' => $page->id,
            'landing_page_version_id' => $versionId,
            'visitor_id' => 'direct-visitor',
            'session_id' => 'direct-session',
            'current_url' => 'https://learn.bluxor.com/go/source-page',
            'path' => '/go/source-page',
        ])->assertOk();

        $this->postJson('/api/v1/analytics/events', [
            'event_name' => 'landing_page_view',
            'landing_page_id' => $page->id,
            'landing_page_version_id' => $versionId,
            'visitor_id' => 'google-visitor',
            'session_id' => 'google-session',
            'current_url' => 'https://learn.bluxor.com/go/source-page',
            'path' => '/go/source-page',
            'referrer' => 'https://www.google.com/search?q=n8n',
        ])->assertOk();

        $this->assertDatabaseHas('analytics_events', ['landing_page_id' => $page->id, 'source' => 'Direct', 'medium' => 'direct']);
        $this->assertDatabaseHas('analytics_events', ['landing_page_id' => $page->id, 'source' => 'Google', 'medium' => 'organic']);

        $this->actingAs($admin)->getJson('/api/v1/admin/landing-pages/'.$page->id.'/analytics?range=30d')
            ->assertOk()
            ->assertJsonPath('data.visitors', 2)
            ->assertJsonPath('data.sessions', 2);
    }

    public function test_landing_analytics_requires_admin_authentication(): void
    {
        $page = LandingPage::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Private Analytics',
            'slug' => 'private-analytics',
            'status' => 'draft',
        ]);

        $this->getJson('/api/v1/admin/landing-pages/'.$page->id.'/analytics')->assertUnauthorized();
    }

    private function zipUpload(string $name, string $heading = 'Hello', array $extra = []): UploadedFile
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('landing_', true).'.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('manifest.json', json_encode([
            'schemaVersion' => 2,
            'name' => 'N8N Freelancer Funnel',
            'version' => '1.0.0',
            'author' => 'Bluxor',
            'sdkVersion' => '2',
            'entry' => 'dist/index.html',
            'capabilities' => ['product', 'offers', 'checkout', 'analytics'],
        ]));
        $zip->addFromString('dist/index.html', '<!doctype html><html><head><link rel="stylesheet" href="assets/styles.css"></head><body><h1 data-lbx-product-name>'.$heading.'</h1><button data-lbx-checkout="single">Buy</button><span data-lbx-offer-price="single"></span><img data-lbx-product-cover alt=""></body></html>');
        $zip->addFromString('dist/assets/styles.css', 'body{font-family:Arial,sans-serif}');
        foreach ($extra as $entry => $body) {
            $zip->addFromString($entry, $body);
        }
        $zip->close();

        return new UploadedFile($path, $name, 'application/zip', null, true);
    }

    private function useTempStorage(): void
    {
        $private = sys_get_temp_dir().DIRECTORY_SEPARATOR.'learn_bluxor_lp_private_'.uniqid();
        $public = sys_get_temp_dir().DIRECTORY_SEPARATOR.'learn_bluxor_lp_public_'.uniqid();
        File::ensureDirectoryExists($private);
        File::ensureDirectoryExists($public);
        config(['filesystems.disks.private.root' => $private]);
        app()->useStoragePath(dirname($public));
    }
}
