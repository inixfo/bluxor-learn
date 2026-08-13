<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\PublicMediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminCategoryController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Category::withCount('products')->orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function show(Category $category)
    {
        return response()->json(['data' => $category->loadCount('products')]);
    }

    public function store(Request $request, PublicMediaService $media)
    {
        $data = $this->data($request);
        if ($request->hasFile('image')) {
            $data['image_path'] = $media->storeImage($request->file('image'), 'category-images');
        }

        $category = Category::create(['uuid' => (string) Str::uuid()] + $data);

        return response()->json(['data' => $category->loadCount('products')], 201);
    }

    public function update(Request $request, Category $category, PublicMediaService $media)
    {
        $data = $this->data($request, $category);

        if ($request->boolean('remove_image')) {
            $media->deleteIfManaged($category->image_path);
            $data['image_path'] = null;
        }

        if ($request->hasFile('image')) {
            $old = $category->image_path;
            $data['image_path'] = $media->storeImage($request->file('image'), 'category-images');
            $media->deleteIfManaged($old);
        }

        $category->update($data);

        return response()->json(['data' => $category->fresh()->loadCount('products')]);
    }

    public function destroy(Category $category)
    {
        abort_if($category->products()->exists(), 422, 'Reassign products before deleting this category.');
        $category->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    private function data(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => [$category ? 'sometimes' : 'required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'slug' => [$category ? 'sometimes' : 'required', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($category)],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,inactive'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
        ]);
    }
}
