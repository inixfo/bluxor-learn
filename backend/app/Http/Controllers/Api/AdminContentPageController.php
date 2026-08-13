<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminContentPageController extends Controller
{
    public function index()
    {
        return response()->json(['data' => ContentPage::orderBy('slug')->get()]);
    }

    public function show(ContentPage $contentPage)
    {
        return response()->json(['data' => $contentPage]);
    }

    public function store(Request $request)
    {
        $data = $this->data($request);
        $page = ContentPage::create(['uuid' => (string) Str::uuid()] + $data);

        return response()->json(['data' => $page], 201);
    }

    public function update(Request $request, ContentPage $contentPage)
    {
        $contentPage->update($this->data($request, $contentPage));

        return response()->json(['data' => $contentPage->fresh()]);
    }

    private function data(Request $request, ?ContentPage $page = null): array
    {
        return $request->validate([
            'title' => [$page ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => [$page ? 'sometimes' : 'required', 'string', 'max:255', Rule::unique('content_pages', 'slug')->ignore($page)],
            'content' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'in:draft,published'],
        ]);
    }
}
