<?php

namespace Tests\Feature;

use App\Mail\AdminDiagnosticMail;
use App\Mail\ContactInquiryMail;
use App\Models\AdminNotification;
use App\Models\Category;
use App\Models\ContentPage;
use App\Models\LandingPage;
use App\Models\LandingPageVersion;
use App\Models\Product;
use App\Models\SocialAccount;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionFeaturesPhase2Test extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_queue_smtp_test_email_and_customer_cannot(): void
    {
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $customer = User::where('email', 'rakib@example.com')->firstOrFail();

        $this->actingAs($customer)->postJson('/api/v1/admin/settings/email/test', ['email' => 'owner@example.com'])->assertForbidden();

        $this->actingAs($admin)->postJson('/api/v1/admin/settings/email/test', ['email' => 'owner@example.com'])
            ->assertOk()
            ->assertJsonPath('data.message', 'Test email queued.');

        Mail::assertQueued(AdminDiagnosticMail::class);
    }

    public function test_product_image_upload_validation_and_replacement(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->useTempPublicDisk();
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $category = Category::firstOrFail();

        $productId = $this->actingAs($admin)->post('/api/v1/admin/products', [
            'name' => 'Image Product',
            'slug' => 'image-product',
            'category_id' => $category->id,
            'product_type' => 'ebook',
            'regular_price_minor' => 10000,
            'currency' => 'BDT',
            'status' => 'draft',
            'cover_image' => $this->pngUpload('cover.png'),
        ])->assertCreated()->assertJsonPath('data.category_id', $category->id)->json('data.id');

        $product = Product::findOrFail($productId);
        $this->assertStringContainsString('/storage/product-images/', (string) $product->cover_image_path);
        Storage::disk('public')->assertExists(Str::after($product->cover_image_path, '/storage/'));

        $old = $product->cover_image_path;
        $this->actingAs($admin)->post('/api/v1/admin/products/'.$product->id, [
            '_method' => 'PATCH',
            'name' => 'Image Product Updated',
            'cover_image' => $this->pngUpload('replacement.png'),
        ])->assertOk();

        $product->refresh();
        $this->assertNotSame($old, $product->cover_image_path);
        Storage::disk('public')->assertMissing(Str::after($old, '/storage/'));

        $this->actingAs($admin)->post('/api/v1/admin/products', [
            'name' => 'Bad Image',
            'slug' => 'bad-image',
            'product_type' => 'ebook',
            'regular_price_minor' => 10000,
            'currency' => 'BDT',
            'status' => 'draft',
            'cover_image' => UploadedFile::fake()->create('bad.txt', 1, 'text/plain'),
        ])->assertStatus(302);
    }

    public function test_landing_page_product_can_be_reassigned_and_runtime_context_updates(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $first = Product::where('slug', 'ai-automation-n8n')->firstOrFail();
        $second = Product::where('slug', 'practical-bug-bounty')->firstOrFail();

        $page = LandingPage::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Switchable',
            'slug' => 'switchable',
            'status' => 'published',
            'primary_product_id' => $first->id,
            'created_by' => $admin->id,
        ]);
        $version = LandingPageVersion::create([
            'uuid' => (string) Str::uuid(),
            'landing_page_id' => $page->id,
            'version_number' => 1,
            'package_path' => 'landing-pages/switchable/v1/source.zip',
            'public_path' => 'landing-pages/switchable/v1/public',
            'manifest' => ['schemaVersion' => 2, 'sdkVersion' => '2'],
            'entry_path' => 'dist/index.html',
            'checksum' => 'test',
            'sdk_version' => '2',
            'status' => 'published',
            'created_by' => $admin->id,
        ]);
        $page->forceFill(['published_version_id' => $version->id])->save();
        $page->offers()->create(['offer_key' => 'single', 'offer_type' => 'product', 'product_id' => $first->id, 'is_primary' => true]);

        $this->actingAs($admin)->patchJson('/api/v1/admin/landing-pages/'.$page->id.'/product', [
            'primary_product_id' => $second->id,
        ])->assertOk()->assertJsonPath('data.primary_product_id', $second->id);

        $this->getJson('/api/v1/landing-pages/switchable/context')
            ->assertOk()
            ->assertJsonPath('data.product.slug', 'practical-bug-bounty')
            ->assertJsonPath('data.offers.single.slug', 'practical-bug-bounty');
    }

    public function test_content_pages_contact_and_notifications(): void
    {
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();

        $this->getJson('/api/v1/content-pages/about')->assertOk()->assertJsonPath('data.slug', 'about');

        $this->actingAs($admin)->patchJson('/api/v1/admin/content-pages/'.ContentPage::where('slug', 'help')->value('id'), [
            'content' => 'Updated help copy.',
        ])->assertOk()->assertJsonPath('data.content', 'Updated help copy.');

        $this->postJson('/api/v1/contact', [
            'name' => 'Buyer',
            'email' => 'buyer@example.com',
            'subject' => 'Access question',
            'message' => 'I need help accessing my download.',
        ])->assertCreated();

        Mail::assertQueued(ContactInquiryMail::class);
        $this->assertSame(1, AdminNotification::where('type', 'contact.submitted')->count());

        $notificationId = $this->actingAs($admin)->getJson('/api/v1/admin/notifications')
            ->assertOk()
            ->json('data.0.id');
        $this->actingAs($admin)->getJson('/api/v1/admin/notifications/unread-count')->assertJsonPath('data.count', 1);
        $this->actingAs($admin)->postJson('/api/v1/admin/notifications/'.$notificationId.'/read')->assertOk();
        $this->actingAs($admin)->getJson('/api/v1/admin/notifications/unread-count')->assertJsonPath('data.count', 0);
    }

    public function test_category_management_ordering_public_listing_and_safe_delete(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->useTempPublicDisk();
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();

        $id = $this->actingAs($admin)->post('/api/v1/admin/categories', [
            'name' => 'Zapier',
            'slug' => 'zapier',
            'description' => 'Automation templates.',
            'status' => 'active',
            'sort_order' => 1,
            'image' => $this->pngUpload('category.png'),
        ])->assertCreated()->json('data.id');

        $this->getJson('/api/v1/categories')->assertOk()->assertJsonFragment(['slug' => 'zapier']);

        $this->actingAs($admin)->post('/api/v1/admin/categories/'.$id, [
            '_method' => 'PATCH',
            'status' => 'inactive',
            'sort_order' => 2,
        ])->assertOk()->assertJsonPath('data.status', 'inactive');

        $this->getJson('/api/v1/categories')->assertOk()->assertJsonMissing(['slug' => 'zapier']);

        $used = Category::whereHas('products')->firstOrFail();
        $this->actingAs($admin)->deleteJson('/api/v1/admin/categories/'.$used->id)->assertStatus(422);
    }

    public function test_google_oauth_creates_and_reuses_user_with_safe_checkout_return(): void
    {
        $this->seed(DatabaseSeeder::class);
        config([
            'services.google.client_id' => 'google-client',
            'services.google.client_secret' => 'google-secret',
            'services.google.redirect_uri' => 'https://learn.test/api/v1/auth/google/callback',
            'app.frontend_url' => 'https://learn.test',
        ]);

        $redirect = $this->getJson('/api/v1/auth/google/redirect?return_to=/checkout?product_id=1')->assertOk()->json('data.url');
        parse_str(parse_url($redirect, PHP_URL_QUERY), $query);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token']),
            'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'sub' => 'google-123',
                'name' => 'Google Buyer',
                'email' => 'google@example.com',
                'email_verified' => true,
            ]),
        ]);

        $this->get('/api/v1/auth/google/callback?state='.$query['state'].'&code=ok')
            ->assertRedirect('https://learn.test/checkout?product_id=1');

        $this->assertDatabaseHas('users', ['email' => 'google@example.com']);
        $this->assertSame(1, SocialAccount::where('provider', 'google')->where('provider_user_id', 'google-123')->count());
        $this->assertAuthenticated();

        $this->postJson('/api/v1/checkout/orders', [
            'product_id' => Product::where('slug', 'ai-automation-n8n')->value('id'),
            'customer_name' => 'Google Buyer',
            'customer_email' => 'google@example.com',
            'payment_method' => 'card',
        ])->assertCreated();
    }

    private function pngUpload(string $name): UploadedFile
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('img_', true).'.png';
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function useTempPublicDisk(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'learn_bluxor_public_test_'.uniqid();
        File::ensureDirectoryExists($root);
        config([
            'filesystems.disks.public.root' => $root,
            'filesystems.disks.public.url' => 'http://localhost/storage',
        ]);
    }
}
