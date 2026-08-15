<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FaqCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminFaqCategoryController extends Controller
{
    public function index()
    {
        return response()->json(['data' => FaqCategory::withCount('items')->orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $category = FaqCategory::create($this->data($request));

        return response()->json(['data' => $category], 201);
    }

    public function update(Request $request, FaqCategory $faqCategory)
    {
        $faqCategory->update($this->data($request, $faqCategory));

        return response()->json(['data' => $faqCategory->fresh()->loadCount('items')]);
    }

    public function destroy(FaqCategory $faqCategory)
    {
        $faqCategory->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    private function data(Request $request, ?FaqCategory $category = null): array
    {
        return $request->validate([
            'name' => [$category ? 'sometimes' : 'required', 'string', 'max:120'],
            'slug' => [$category ? 'sometimes' : 'required', 'string', 'max:120', Rule::unique('faq_categories', 'slug')->ignore($category)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
    }
}
