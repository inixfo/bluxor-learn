<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\LandingPage;
use App\Models\LandingPageOffer;
use App\Models\LandingPageVersion;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LandingPageEngine
{
    public function __construct(
        private readonly LandingPagePackageValidator $validator,
        private readonly AuditLogger $audit
    ) {}

    public function upload(UploadedFile $package, array $metadata, int $adminId): LandingPageVersion
    {
        $tmpPath = $package->getRealPath();
        $report = $this->validator->validate($tmpPath);
        $manifest = $report['manifest'];
        $slug = Str::slug($metadata['slug'] ?? $manifest['name']);

        return DB::transaction(function () use ($package, $metadata, $adminId, $report, $manifest, $slug, $tmpPath) {
            $page = LandingPage::firstOrCreate(
                ['slug' => $slug],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $metadata['name'] ?? $manifest['name'],
                    'status' => 'draft',
                    'primary_product_id' => $metadata['primary_product_id'] ?? null,
                    'created_by' => $adminId,
                ]
            );

            $page->fill([
                'name' => $metadata['name'] ?? $page->name,
                'primary_product_id' => $metadata['primary_product_id'] ?? $page->primary_product_id,
            ])->save();

            $versionNumber = ((int) $page->versions()->max('version_number')) + 1;
            $base = 'landing-pages/'.$page->slug.'/v'.$versionNumber;
            $originalPath = $package->storeAs($base, 'source.zip', 'private');
            $publicPath = 'landing-pages/'.$page->slug.'/v'.$versionNumber.'/public';

            $target = storage_path('app/public/'.$publicPath);
            $this->validator->extractPublicAssets($tmpPath, $target);

            $version = LandingPageVersion::create([
                'uuid' => (string) Str::uuid(),
                'landing_page_id' => $page->id,
                'version_number' => $versionNumber,
                'package_path' => $originalPath,
                'original_package_path' => $originalPath,
                'public_path' => $publicPath,
                'package_size_bytes' => $package->getSize(),
                'manifest' => $manifest,
                'entry_path' => $manifest['entry'],
                'checksum' => hash_file('sha256', $tmpPath),
                'sdk_version' => (string) $manifest['sdkVersion'],
                'status' => 'validated',
                'validation_report' => $report,
                'created_by' => $adminId,
            ]);

            $this->syncOffers($page, $version, $metadata['offers'] ?? []);
            $this->audit->log('landing_page.uploaded', $version, ['slug' => $page->slug, 'version' => $versionNumber]);

            return $version->load('landingPage.offers.product', 'landingPage.offers.bundle');
        });
    }

    public function syncOffers(LandingPage $page, ?LandingPageVersion $version, array $offers): void
    {
        if ($offers === []) {
            if ($page->primary_product_id) {
                $offers = [[
                    'offer_key' => 'single',
                    'offer_type' => 'product',
                    'product_id' => $page->primary_product_id,
                    'is_primary' => true,
                ]];
            }
        }

        $keys = [];
        foreach ($offers as $index => $offer) {
            $key = $offer['offer_key'] ?? $offer['key'] ?? 'single';
            $type = $offer['offer_type'] ?? $offer['type'] ?? 'product';
            $normalizedKey = Str::slug((string) $key, '-');

            if ($normalizedKey === '') {
                throw ValidationException::withMessages(['offers' => ['Offer keys must contain letters or numbers.']]);
            }

            if (in_array($normalizedKey, $keys, true)) {
                throw ValidationException::withMessages(['offers' => ["Duplicate offer key `{$normalizedKey}`."]]);
            }

            $keys[] = $normalizedKey;

            if ($type === 'product' && empty($offer['product_id'])) {
                throw ValidationException::withMessages(['offers' => ["Offer {$normalizedKey} requires product_id."]]);
            }

            if ($type === 'bundle' && empty($offer['bundle_id'])) {
                throw ValidationException::withMessages(['offers' => ["Offer {$normalizedKey} requires bundle_id."]]);
            }

            if ($type === 'product') {
                Product::whereKey($offer['product_id'])->where('status', 'published')->firstOrFail();
            } else {
                Bundle::whereKey($offer['bundle_id'])->where('status', 'published')->firstOrFail();
            }

            LandingPageOffer::updateOrCreate(
                ['landing_page_id' => $page->id, 'offer_key' => $normalizedKey],
                [
                    'landing_page_version_id' => $version?->id,
                    'offer_type' => $type,
                    'product_id' => $type === 'product' ? $offer['product_id'] : null,
                    'bundle_id' => $type === 'bundle' ? $offer['bundle_id'] : null,
                    'sort_order' => $offer['sort_order'] ?? $index,
                    'is_primary' => (bool) ($offer['is_primary'] ?? $index === 0),
                ]
            );
        }

        LandingPageOffer::where('landing_page_id', $page->id)->whereNotIn('offer_key', $keys)->delete();

        $primary = LandingPageOffer::where('landing_page_id', $page->id)->whereIn('offer_key', $keys)->where('is_primary', true)->orderBy('sort_order')->first();
        $primary ??= LandingPageOffer::where('landing_page_id', $page->id)->whereIn('offer_key', $keys)->orderBy('sort_order')->first();
        LandingPageOffer::where('landing_page_id', $page->id)->whereIn('offer_key', $keys)->update(['is_primary' => false]);
        if ($primary) {
            $primary->forceFill(['is_primary' => true])->save();
        }

        $this->audit->log('landing_page.offers_updated', $page, ['offer_keys' => $keys, 'version_id' => $version?->id]);
    }

    public function publish(LandingPage $page, LandingPageVersion $version): LandingPage
    {
        abort_unless((int) $version->landing_page_id === (int) $page->id, 404);

        return DB::transaction(function () use ($page, $version) {
            $version->forceFill(['status' => 'published', 'published_at' => now()])->save();
            $page->forceFill([
                'status' => 'published',
                'published_version_id' => $version->id,
            ])->save();

            $this->audit->log('landing_page.published', $page, ['version_id' => $version->id, 'version' => $version->version_number]);

            return $page->fresh('publishedVersion');
        });
    }

    public function unpublish(LandingPage $page): LandingPage
    {
        $page->forceFill(['status' => 'unpublished'])->save();
        $this->audit->log('landing_page.unpublished', $page);

        return $page->fresh('publishedVersion');
    }

    public function context(LandingPage $page, LandingPageVersion $version, bool $preview = false): array
    {
        $page->loadMissing('primaryProduct.category', 'offers.product', 'offers.bundle.products');
        $primaryProduct = $page->primaryProduct && $page->primaryProduct->status === 'published' ? $page->primaryProduct : null;
        $offers = $page->offers
            ->filter(fn (LandingPageOffer $offer) => $this->offerIsAvailable($offer))
            ->sortBy('sort_order')
            ->mapWithKeys(fn ($offer) => [
                $offer->offer_key => $this->offerPayload($offer),
            ]);

        return [
            'page' => [
                'id' => $page->uuid,
                'slug' => $page->slug,
                'name' => $page->name,
                'version' => (string) $version->version_number,
                'preview' => $preview,
            ],
            'product' => $primaryProduct ? $this->productPayload($primaryProduct) : null,
            'offers' => $offers,
            'analytics' => [
                'landing_page_id' => $page->id,
                'landing_page_version_id' => $version->id,
            ],
        ];
    }

    public function entryFile(LandingPageVersion $version): string
    {
        $relative = preg_replace('#^dist/#', '', $version->entry_path);
        $path = storage_path('app/public/'.$version->public_path.'/dist/'.$relative);
        abort_unless(File::exists($path), 404);

        return $path;
    }

    public function assetFile(LandingPageVersion $version, string $assetPath): string
    {
        $clean = str_replace('\\', '/', $assetPath);
        abort_if(str_contains($clean, '../') || Str::startsWith($clean, ['/']), 404);
        $path = storage_path('app/public/'.$version->public_path.'/'.$clean);
        if (! File::exists($path)) {
            $path = storage_path('app/public/'.$version->public_path.'/dist/'.$clean);
        }
        abort_unless(File::exists($path), 404);

        return $path;
    }

    public function sourceDownload(LandingPageVersion $version): string
    {
        $path = $version->original_package_path ?: $version->package_path;
        abort_unless(Storage::disk('private')->exists($path), 404);

        return Storage::disk('private')->path($path);
    }

    private function offerPayload(LandingPageOffer $offer): array
    {
        $item = $offer->offer_type === 'bundle' ? $offer->bundle : $offer->product;
        $minor = $offer->offer_type === 'bundle'
            ? ($item->sale_price_minor ?? $item->bundle_price_minor)
            : ($item->sale_price_minor ?? $item->regular_price_minor);

        return [
            'key' => $offer->offer_key,
            'type' => $offer->offer_type,
            'id' => $item->uuid,
            'backend_id' => $item->id,
            'content_id' => $offer->offer_type.':'.$item->id,
            'name' => $item->name,
            'slug' => $item->slug,
            'price_minor' => $minor,
            'regular_price_minor' => $offer->offer_type === 'bundle' ? $item->regular_value_minor : $item->regular_price_minor,
            'saving_minor' => max(0, ($offer->offer_type === 'bundle' ? $item->regular_value_minor : $item->regular_price_minor) - $minor),
            'currency' => $item->currency,
            'formatted_price' => $item->currency.' '.number_format($minor / 100),
            'checkout_url' => rtrim((string) env('FRONTEND_URL', url('/')), '/').'/checkout?'.http_build_query(['lp' => $offer->landingPage?->slug, 'offer' => $offer->offer_key]),
            'is_primary' => $offer->is_primary,
        ];
    }

    private function productPayload(Product $product): array
    {
        return [
            'id' => $product->uuid,
            'backend_id' => $product->id,
            'content_id' => 'product:'.$product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'cover' => $product->cover_image_path,
            'description' => $product->description,
            'short_description' => $product->short_description,
            'category' => $product->category?->name,
            'currency' => $product->currency,
            'price_minor' => $product->sale_price_minor ?? $product->regular_price_minor,
            'regular_price_minor' => $product->regular_price_minor,
            'sale_price_minor' => $product->sale_price_minor,
            'formatted_price' => $product->currency.' '.number_format(($product->sale_price_minor ?? $product->regular_price_minor) / 100),
        ];
    }

    private function offerIsAvailable(LandingPageOffer $offer): bool
    {
        if ($offer->offer_type === 'product') {
            return $offer->product && $offer->product->status === 'published';
        }

        if (! $offer->bundle || $offer->bundle->status !== 'published') {
            return false;
        }

        return $offer->bundle->products->every(fn (Product $product) => $product->status === 'published');
    }
}
