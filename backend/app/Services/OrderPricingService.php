<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\Coupon;
use App\Models\LandingPage;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderPricingService
{
    public function quote(array $payload): array
    {
        [$type, $item] = $this->resolvePurchasable($payload);
        $currency = $item->currency;
        $unit = $type === 'bundle'
            ? ($item->sale_price_minor ?? $item->bundle_price_minor)
            : ($item->sale_price_minor ?? $item->regular_price_minor);
        $subtotal = $unit;
        $coupon = $this->resolveCoupon($payload['coupon_code'] ?? null, $subtotal, $currency, $type, $item);
        $discount = $coupon ? $this->discountFor($coupon, $subtotal) : 0;
        $total = max(0, $subtotal - $discount);

        return [
            'type' => $type,
            'item' => $item,
            'coupon' => $coupon,
            'subtotal_minor' => $subtotal,
            'discount_minor' => $discount,
            'total_minor' => $total,
            'currency' => $currency,
            'landing_page' => $payload['_landing_page'] ?? null,
            'landing_page_version' => $payload['_landing_page_version'] ?? null,
            'offer_key' => $payload['offer_key'] ?? null,
        ];
    }

    private function resolvePurchasable(array $payload): array
    {
        if (! empty($payload['landing_page_id']) || ! empty($payload['landing_page_slug'])) {
            return $this->resolveLandingOffer($payload);
        }

        if (! empty($payload['bundle_id'])) {
            $bundle = Bundle::with('products.files')->whereKey($payload['bundle_id'])->where('status', 'published')->first();

            if (! $bundle) {
                throw ValidationException::withMessages(['bundle_id' => ['Bundle is not available.']]);
            }

            return ['bundle', $bundle];
        }

        $productId = $payload['product_id'] ?? null;
        $slug = $payload['slug'] ?? null;
        $product = Product::with('category', 'files', 'tags')
            ->where('status', 'published')
            ->when($productId, fn ($query) => $query->whereKey($productId))
            ->when($slug, fn ($query) => $query->where('slug', $slug))
            ->first();

        if (! $product) {
            throw ValidationException::withMessages(['product_id' => ['Product is not available.']]);
        }

        return ['product', $product];
    }

    private function resolveLandingOffer(array &$payload): array
    {
        $page = LandingPage::with('publishedVersion', 'offers.product', 'offers.bundle')
            ->where('status', 'published')
            ->when($payload['landing_page_id'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->when($payload['landing_page_slug'] ?? null, fn ($query, $slug) => $query->where('slug', $slug))
            ->first();

        if (! $page || ! $page->publishedVersion) {
            throw ValidationException::withMessages(['landing_page' => ['Landing page is not available.']]);
        }

        $offerKey = $payload['offer_key'] ?? 'single';
        $offer = $page->offers->firstWhere('offer_key', $offerKey);
        if (! $offer) {
            throw ValidationException::withMessages(['offer_key' => ['Landing page offer is not available.']]);
        }

        $payload['_landing_page'] = $page;
        $payload['_landing_page_version'] = $page->publishedVersion;

        if ($offer->offer_type === 'bundle') {
            $bundle = Bundle::with('products.files')->whereKey($offer->bundle_id)->where('status', 'published')->first();
            if (! $bundle) {
                throw ValidationException::withMessages(['offer_key' => ['Landing page bundle offer is not available.']]);
            }

            return ['bundle', $bundle];
        }

        $product = Product::with('category', 'files', 'tags')->whereKey($offer->product_id)->where('status', 'published')->first();
        if (! $product) {
            throw ValidationException::withMessages(['offer_key' => ['Landing page product offer is not available.']]);
        }

        return ['product', $product];
    }

    private function resolveCoupon(?string $code, int $subtotal, string $currency, string $type, Product|Bundle $item): ?Coupon
    {
        if (! $code) {
            return null;
        }

        $coupon = Coupon::with('products:id', 'bundles:id')->where('code', strtoupper($code))->where('status', 'active')->first();

        if (! $coupon || $coupon->currency !== $currency || $coupon->minimum_order_minor > $subtotal) {
            throw ValidationException::withMessages(['coupon_code' => ['Coupon is invalid for this order.']]);
        }

        $now = now();
        if (($coupon->starts_at && $coupon->starts_at->isFuture()) || ($coupon->expires_at && $coupon->expires_at->isPast())) {
            throw ValidationException::withMessages(['coupon_code' => ['Coupon is not active.']]);
        }

        if ($coupon->usage_limit && DB::table('coupon_usages')->where('coupon_id', $coupon->id)->count() >= $coupon->usage_limit) {
            throw ValidationException::withMessages(['coupon_code' => ['Coupon usage limit has been reached.']]);
        }

        if ($type === 'product' && $coupon->products->isNotEmpty() && ! $coupon->products->contains('id', $item->id)) {
            throw ValidationException::withMessages(['coupon_code' => ['Coupon is not valid for this product.']]);
        }

        if ($type === 'bundle' && $coupon->bundles->isNotEmpty() && ! $coupon->bundles->contains('id', $item->id)) {
            throw ValidationException::withMessages(['coupon_code' => ['Coupon is not valid for this bundle.']]);
        }

        return $coupon;
    }

    private function discountFor(Coupon $coupon, int $subtotal): int
    {
        if ($coupon->type === 'percent') {
            return (int) floor($subtotal * $coupon->percentage_bps / 10000);
        }

        return min($subtotal, (int) $coupon->amount_minor);
    }
}
