<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HelpCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminHelpCategoryController extends Controller
{
    public function index()
    {
        return response()->json(['data' => HelpCategory::withCount('articles')->orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $category = HelpCategory::create($this->data($request));

        return response()->json(['data' => $category], 201);
    }

    public function update(Request $request, HelpCategory $helpCategory)
    {
        $helpCategory->update($this->data($request, $helpCategory));

        return response()->json(['data' => $helpCategory->fresh()->loadCount('articles')]);
    }

    public function destroy(HelpCategory $helpCategory)
    {
        $helpCategory->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    private function data(Request $request, ?HelpCategory $category = null): array
    {
        return $request->validate([
            'name' => [$category ? 'sometimes' : 'required', 'string', 'max:120'],
            'slug' => [$category ? 'sometimes' : 'required', 'string', 'max:120', Rule::unique('help_categories', 'slug')->ignore($category)],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
    }
}
