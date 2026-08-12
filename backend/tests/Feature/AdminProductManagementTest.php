<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_show_publish_archive_and_upload_product_file(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['filesystems.disks.private.root' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'learn-bluxor-admin-test']);
        File::ensureDirectoryExists(config('filesystems.disks.private.root'));

        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $categoryId = Category::where('slug', 'ai')->value('id');

        $productId = $this->actingAs($admin)->postJson('/api/v1/admin/products', [
            'name' => 'Production Ready Laravel',
            'slug' => 'production-ready-laravel',
            'category_id' => $categoryId,
            'product_type' => 'ebook',
            'regular_price_minor' => 250000,
            'sale_price_minor' => 190000,
            'currency' => 'BDT',
            'status' => 'draft',
            'short_description' => 'Ship Laravel apps with confidence.',
            'description' => 'A practical backend production checklist.',
            'cover_image_path' => 'https://example.com/cover.jpg',
        ])->assertCreated()->json('data.id');

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/products/'.$productId)
            ->assertOk()
            ->assertJsonPath('data.slug', 'production-ready-laravel');

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/products/'.$productId.'/publish')
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->actingAs($admin)
            ->post('/api/v1/admin/products/'.$productId.'/files', [
                'file' => UploadedFile::fake()->create('laravel-checklist.pdf', 128, 'application/pdf'),
                'version' => '1.0.0',
            ])
            ->assertCreated()
            ->assertJsonPath('data.storage_disk', 'private')
            ->assertJsonPath('data.status', 'active');

        $this->assertSame(1, Product::findOrFail($productId)->files()->count());

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/products/'.$productId.'/archive')
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');
    }
}
