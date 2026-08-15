<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Resource;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResourceLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_resource_attach_product_and_copy_stable_link(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->useTempPrivateDisk();

        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();

        $response = $this->actingAs($admin)->post('/api/v1/admin/resources', [
            'title' => 'Project 12 Lead Capture Workflow',
            'slug' => 'project-12-lead-capture-workflow',
            'description' => 'n8n workflow used inside the ebook.',
            'resource_type' => 'n8n Workflow',
            'source_type' => 'uploaded_file',
            'access_type' => 'public',
            'status' => 'published',
            'version' => '1.0',
            'product_ids' => [$product->id],
            'file' => UploadedFile::fake()->create('workflow.json', 4, 'application/json'),
        ])->assertCreated();

        $path = $response->json('data.storage_path');
        Storage::disk('private')->assertExists($path);

        $resource = Resource::where('slug', 'project-12-lead-capture-workflow')->firstOrFail();
        $this->assertSame('/r/project-12-lead-capture-workflow', '/r/'.$resource->slug);
        $this->assertTrue($resource->products()->whereKey($product->id)->exists());
        $this->assertSame(1, $resource->versions()->count());
    }

    public function test_invalid_file_is_rejected_and_customers_cannot_manage_resources(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->useTempPrivateDisk();

        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $customer = User::where('email', 'rakib@example.com')->firstOrFail();

        $this->actingAs($customer)
            ->getJson('/api/v1/admin/resources')
            ->assertForbidden();

        $this->actingAs($admin)->post('/api/v1/admin/resources', [
            'title' => 'Unsafe Executable',
            'slug' => 'unsafe-executable',
            'resource_type' => 'Other',
            'source_type' => 'uploaded_file',
            'status' => 'published',
            'file' => UploadedFile::fake()->create('payload.exe', 1, 'application/octet-stream'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_public_resource_downloads_from_canonical_slug_and_tracks_count(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->useTempPrivateDisk();

        $resource = $this->createUploadedResource('public-workflow', 'public');
        Storage::disk('private')->put($resource->storage_path, '{"nodes":[]}');

        $this->getJson('/api/v1/resources/public-workflow')
            ->assertOk()
            ->assertJsonPath('data.canonical_url', '/r/public-workflow')
            ->assertJsonPath('data.authorized', true);

        $this->get('/api/v1/resources/public-workflow/download')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertSame(1, $resource->fresh()->download_count);
    }

    public function test_purchase_required_resource_blocks_unauthorized_and_allows_entitled_customer(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->useTempPrivateDisk();

        $product = Product::where('slug', 'ai-automation-n8n')->firstOrFail();
        $resource = $this->createUploadedResource('purchase-workflow', 'purchase_required', [$product->id]);
        Storage::disk('private')->put($resource->storage_path, '{"paid":true}');

        $this->getJson('/api/v1/resources/purchase-workflow')
            ->assertOk()
            ->assertJsonPath('data.authorized', false);

        $this->getJson('/api/v1/resources/purchase-workflow/download')->assertForbidden();

        $customer = User::where('email', 'rakib@example.com')->firstOrFail();
        $this->actingAs($customer)
            ->get('/api/v1/resources/purchase-workflow/download')
            ->assertOk();

        $this->assertSame(1, $resource->fresh()->download_count);
    }

    public function test_replacing_resource_file_keeps_canonical_slug_and_records_version(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->useTempPrivateDisk();

        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $resource = $this->createUploadedResource('stable-workflow', 'public');
        $firstPath = $resource->storage_path;

        $this->actingAs($admin)->post('/api/v1/admin/resources/'.$resource->id, [
            '_method' => 'PATCH',
            'version' => '1.1',
            'file' => UploadedFile::fake()->create('workflow-v1-1.json', 4, 'application/json'),
        ])->assertOk()
            ->assertJsonPath('data.slug', 'stable-workflow')
            ->assertJsonPath('data.version', '1.1');

        $resource->refresh();
        $this->assertSame('/r/stable-workflow', '/r/'.$resource->slug);
        $this->assertNotSame($firstPath, $resource->storage_path);
        $this->assertSame(2, $resource->versions()->count());
    }

    public function test_external_resource_redirects_through_stable_url(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();

        $this->actingAs($admin)->postJson('/api/v1/admin/resources', [
            'title' => 'Customer Onboarding Sheet',
            'slug' => 'customer-onboarding-sheet',
            'description' => 'Template spreadsheet.',
            'resource_type' => 'Spreadsheet',
            'source_type' => 'external_url',
            'external_url' => 'https://docs.google.com/spreadsheets/d/example',
            'access_type' => 'public',
            'status' => 'published',
            'version' => '1.0',
        ])->assertCreated();

        $this->getJson('/api/v1/resources/customer-onboarding-sheet')
            ->assertOk()
            ->assertJsonPath('data.canonical_url', '/r/customer-onboarding-sheet')
            ->assertJsonPath('data.source_type', 'external_url');

        $this->get('/api/v1/resources/customer-onboarding-sheet/download')
            ->assertRedirect('https://docs.google.com/spreadsheets/d/example');

        $this->assertSame(1, Resource::where('slug', 'customer-onboarding-sheet')->firstOrFail()->download_count);
    }

    public function test_archived_resource_page_remains_visible_but_download_is_unavailable(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->useTempPrivateDisk();

        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $resource = $this->createUploadedResource('archived-workflow', 'public');

        $this->actingAs($admin)->postJson('/api/v1/admin/resources/'.$resource->id.'/archive')
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->getJson('/api/v1/resources/archived-workflow')
            ->assertOk()
            ->assertJsonPath('data.status', 'archived')
            ->assertJsonPath('data.download_url', null);

        $this->getJson('/api/v1/resources/archived-workflow/download')->assertNotFound();
    }

    private function createUploadedResource(string $slug, string $accessType, array $productIds = []): Resource
    {
        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();

        $payload = $this->actingAs($admin)->post('/api/v1/admin/resources', [
            'title' => str($slug)->replace('-', ' ')->title()->toString(),
            'slug' => $slug,
            'description' => 'Supplementary resource.',
            'resource_type' => 'n8n Workflow',
            'source_type' => 'uploaded_file',
            'access_type' => $accessType,
            'status' => 'published',
            'version' => '1.0',
            'product_ids' => $productIds,
            'file' => UploadedFile::fake()->create($slug.'.json', 4, 'application/json'),
        ])->assertCreated()->json('data');

        return Resource::findOrFail($payload['id']);
    }

    private function useTempPrivateDisk(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'learn_bluxor_resource_test_'.uniqid();
        File::ensureDirectoryExists($root);
        config(['filesystems.disks.private.root' => $root]);
    }
}
