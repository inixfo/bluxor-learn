<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Http\Request;

class HelpCenterController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $categories = HelpCategory::withCount(['articles' => fn ($query) => $query->where('status', 'published')])
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $featured = HelpArticle::with('category')
            ->where('status', 'published')
            ->where('is_featured', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->limit(8)
            ->get();

        $results = collect();
        if (! empty($data['q'])) {
            $term = $data['q'];
            $results = HelpArticle::with('category')
                ->where('status', 'published')
                ->where(fn ($query) => $query
                    ->where('title', 'like', "%{$term}%")
                    ->orWhere('summary', 'like', "%{$term}%")
                    ->orWhere('content', 'like', "%{$term}%"))
                ->orderByDesc('is_featured')
                ->orderBy('title')
                ->limit(20)
                ->get();
        }

        return response()->json(['data' => [
            'categories' => $categories,
            'featured_articles' => $featured,
            'results' => $results,
        ]]);
    }

    public function show(string $categorySlug, string $articleSlug)
    {
        $category = HelpCategory::where('slug', $categorySlug)->where('status', 'active')->firstOrFail();
        $article = HelpArticle::with('category')
            ->where('help_category_id', $category->id)
            ->where('slug', $articleSlug)
            ->where('status', 'published')
            ->firstOrFail();

        $article->increment('views');

        $related = HelpArticle::with('category')
            ->where('help_category_id', $category->id)
            ->where('id', '!=', $article->id)
            ->where('status', 'published')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->limit(4)
            ->get();

        return response()->json(['data' => [
            'article' => $article->fresh('category'),
            'related' => $related,
        ]]);
    }
}
