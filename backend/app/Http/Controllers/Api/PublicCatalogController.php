<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class PublicCatalogController extends Controller
{
    public function home()
    {
        $products = Product::with('category', 'tags')->where('status', 'published')->latest('published_at')->get();

        return response()->json([
            'data' => [
                'featured_products' => $products->where('featured', true)->values()->map(fn ($product) => $this->product($product)),
                'new_arrivals' => $products->take(4)->values()->map(fn ($product) => $this->product($product)),
                'categories' => Category::withCount(['products' => fn ($query) => $query->where('status', 'published')])
                    ->where('status', 'active')
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(),
                'bundles' => Bundle::with('products')->where('status', 'published')->get()->map(fn ($bundle) => $this->bundle($bundle)),
            ],
        ]);
    }

    public function products(Request $request)
    {
        $query = Product::with('category', 'tags')->where('status', 'published');

        $query->when($request->query('q'), fn ($q, $term) => $q->where(fn ($inner) => $inner
            ->where('name', 'like', "%{$term}%")
            ->orWhere('short_description', 'like', "%{$term}%")
            ->orWhere('description', 'like', "%{$term}%")));

        $query->when($request->query('category'), fn ($q, $slug) => $q->whereHas('category', fn ($category) => $category->where('slug', $slug)));
        $query->when($request->query('type'), fn ($q, $type) => $q->where('product_type', $type));

        match ($request->query('sort')) {
            'price-low' => $query->orderByRaw('coalesce(sale_price_minor, regular_price_minor) asc'),
            'price-high' => $query->orderByRaw('coalesce(sale_price_minor, regular_price_minor) desc'),
            'newest' => $query->latest('published_at'),
            default => $query->latest('featured')->latest('published_at'),
        };

        $products = $query->paginate((int) $request->query('per_page', 12));

        return response()->json([
            'data' => $products->through(fn ($product) => $this->product($product))->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function categories()
    {
        return response()->json([
            'data' => Category::withCount(['products' => fn ($query) => $query->where('status', 'published')])
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function catalog(string $slug)
    {
        $product = Product::with('category', 'tags', 'files')->where('status', 'published')->where('slug', $slug)->first();

        if ($product) {
            return response()->json(['data' => ['kind' => 'product'] + $this->product($product, true)]);
        }

        $bundle = Bundle::with('products.category')->where('status', 'published')->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => ['kind' => 'bundle'] + $this->bundle($bundle, true)]);
    }

    public function search(Request $request)
    {
        $term = (string) $request->query('q');

        return response()->json([
            'data' => Product::with('category')
                ->where('status', 'published')
                ->where('name', 'like', "%{$term}%")
                ->limit(8)
                ->get()
                ->map(fn ($product) => $this->product($product)),
        ]);
    }

    private function product(Product $product, bool $detail = false): array
    {
        return [
            'id' => $product->id,
            'uuid' => $product->uuid,
            'slug' => $product->slug,
            'title' => $product->name,
            'title_bn' => $product->name_bn,
            'category' => $product->category?->name,
            'category_slug' => $product->category?->slug,
            'type' => $product->product_type,
            'short_description' => $product->short_description,
            'description' => $detail ? $product->description : null,
            'regular_price_minor' => $product->regular_price_minor,
            'sale_price_minor' => $product->sale_price_minor,
            'currency' => $product->currency,
            'cover' => $product->cover_image_path,
            'featured' => $product->featured,
            'tags' => $product->tags->pluck('name')->values(),
            'files' => $detail ? $product->files->where('status', 'active')->values() : [],
            'metadata' => $product->metadata,
        ];
    }

    private function bundle(Bundle $bundle, bool $detail = false): array
    {
        return [
            'id' => $bundle->id,
            'uuid' => $bundle->uuid,
            'slug' => $bundle->slug,
            'title' => $bundle->name,
            'title_bn' => $bundle->name_bn,
            'description' => $bundle->description,
            'regular_value_minor' => $bundle->regular_value_minor,
            'bundle_price_minor' => $bundle->bundle_price_minor,
            'sale_price_minor' => $bundle->sale_price_minor,
            'currency' => $bundle->currency,
            'cover' => $bundle->cover_image_path,
            'products' => $detail ? $bundle->products->map(fn ($product) => $this->product($product)) : $bundle->products->pluck('id'),
        ];
    }
}
