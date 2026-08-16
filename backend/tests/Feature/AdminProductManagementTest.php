<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function test_admin_product_cover_image_update_replace_remove_and_validation(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->useTempPublicDisk();

        $admin = User::where('email', 'admin@learn.bluxor.test')->firstOrFail();
        $product = Product::where('slug', 'react-templates-pack')->firstOrFail();

        $originalCover = 'https://example.com/original-cover.jpg';
        $product->forceFill(['cover_image_path' => $originalCover])->save();

        $this->actingAs($admin)->post('/api/v1/admin/products/'.$product->id, [
            '_method' => 'PATCH',
            'name' => $product->name,
            'remove_cover_image' => '0',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.cover_image_path', $originalCover);

        $this->actingAs($admin)->post('/api/v1/admin/products/'.$product->id, [
            '_method' => 'PATCH',
            'cover_image' => $this->imageUpload('cover.png'),
            'remove_cover_image' => '0',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.id', $product->id);

        $uploadedCover = $product->fresh()->cover_image_path;
        $this->assertNotNull($uploadedCover);
        $this->assertNotSame($originalCover, $uploadedCover);
        Storage::disk('public')->assertExists($this->storagePathFromUrl($uploadedCover));

        $this->actingAs($admin)->post('/api/v1/admin/products/'.$product->id, [
            '_method' => 'PATCH',
            'cover_image' => $this->imageUpload('cover-v2.png'),
            'remove_cover_image' => '0',
        ], ['Accept' => 'application/json'])
            ->assertOk();

        $replacedCover = $product->fresh()->cover_image_path;
        $this->assertNotNull($replacedCover);
        $this->assertNotSame($uploadedCover, $replacedCover);
        Storage::disk('public')->assertMissing($this->storagePathFromUrl($uploadedCover));
        Storage::disk('public')->assertExists($this->storagePathFromUrl($replacedCover));

        $this->actingAs($admin)->post('/api/v1/admin/products/'.$product->id, [
            '_method' => 'PATCH',
            'remove_cover_image' => '1',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.cover_image_path', null);

        Storage::disk('public')->assertMissing($this->storagePathFromUrl($replacedCover));

        $this->actingAs($admin)->post('/api/v1/admin/products/'.$product->id, [
            '_method' => 'PATCH',
            'cover_image' => UploadedFile::fake()->create('cover.txt', 2, 'text/plain'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cover_image');

        $this->actingAs($admin)->post('/api/v1/admin/products/'.$product->id, [
            '_method' => 'PATCH',
            'cover_image' => $this->imageUpload('huge-cover.png', 6000),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cover_image');
    }

    private function useTempPublicDisk(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'learn-bluxor-public-test-'.uniqid();
        File::ensureDirectoryExists($root);
        config(['filesystems.disks.public.root' => $root]);
    }

    private function storagePathFromUrl(string $url): string
    {
        return Str::after($url, '/storage/');
    }

    private function imageUpload(string $name, int $kilobytes = 2): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
        $path = tempnam(sys_get_temp_dir(), 'cover-image-');
        file_put_contents($path, $png.str_repeat('0', max(0, ($kilobytes * 1024) - strlen($png))));

        return new UploadedFile($path, $name, 'image/png', null, true);
    }
}
