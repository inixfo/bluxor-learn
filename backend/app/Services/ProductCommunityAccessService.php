<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Collection;

class ProductCommunityAccessService
{
    public function forOrder(Order $order): array
    {
        $order->loadMissing('items');

        return $this->dedupe($order->items->flatMap(function ($item) {
            if ($item->product_id) {
                return Product::whereKey($item->product_id)->get();
            }

            if ($item->bundle_id) {
                return Bundle::with('products')->find($item->bundle_id)?->products ?? collect();
            }

            return collect();
        }));
    }

    public function forEntitlements(Collection $entitlements): array
    {
        return $this->dedupe($entitlements->map(fn (Entitlement $entitlement) => $entitlement->product)->filter());
    }

    public function forProduct(Product $product): array
    {
        return $this->dedupe(collect([$product]));
    }

    private function dedupe(Collection $products): array
    {
        return $products
            ->filter(fn (Product $product) => $product->community_enabled && $product->community_name && $product->community_url)
            ->map(fn (Product $product) => [
                'name' => $product->community_name,
                'url' => $product->community_url,
                'product_id' => $product->id,
                'product_name' => $product->name,
                '_key' => $this->normalizeUrl((string) $product->community_url),
            ])
            ->unique('_key')
            ->map(fn (array $community) => [
                'name' => $community['name'],
                'url' => $community['url'],
                'product_id' => $community['product_id'],
                'product_name' => $community['product_name'],
            ])
            ->values()
            ->all();
    }

    private function normalizeUrl(string $url): string
    {
        return rtrim(strtolower(trim($url)), '/');
    }
}
