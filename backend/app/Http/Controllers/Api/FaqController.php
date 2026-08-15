<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FaqCategory;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
        ]);

        $categories = FaqCategory::query()
            ->where('status', 'active')
            ->when($data['category'] ?? null, fn ($query, $slug) => $query->where('slug', $slug))
            ->with(['items' => function ($query) use ($data) {
                $query->where('status', 'active')
                    ->when($data['q'] ?? null, fn ($inner, $term) => $inner->where(fn ($nested) => $nested
                        ->where('question', 'like', "%{$term}%")
                        ->orWhere('answer', 'like', "%{$term}%")))
                    ->orderBy('sort_order')
                    ->orderBy('question');
            }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn ($category) => empty($data['q']) || $category->items->isNotEmpty())
            ->values();

        return response()->json(['data' => $categories]);
    }
}
