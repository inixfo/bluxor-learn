<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminHelpArticleController extends Controller
{
    public function index(Request $request)
    {
        $articles = HelpArticle::with('category')
            ->when($request->query('category_id'), fn ($query, $id) => $query->where('help_category_id', $id))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return response()->json(['data' => $articles]);
    }

    public function store(Request $request)
    {
        $article = HelpArticle::create($this->data($request));

        return response()->json(['data' => $article->load('category')], 201);
    }

    public function update(Request $request, HelpArticle $helpArticle)
    {
        $helpArticle->update($this->data($request, $helpArticle));

        return response()->json(['data' => $helpArticle->fresh('category')]);
    }

    public function destroy(HelpArticle $helpArticle)
    {
        $helpArticle->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    private function data(Request $request, ?HelpArticle $article = null): array
    {
        $categoryId = $request->input('help_category_id', $article?->help_category_id);

        return $request->validate([
            'help_category_id' => [$article ? 'sometimes' : 'required', 'integer', 'exists:help_categories,id'],
            'title' => [$article ? 'sometimes' : 'required', 'string', 'max:180'],
            'slug' => [
                $article ? 'sometimes' : 'required',
                'string',
                'max:180',
                Rule::unique('help_articles', 'slug')->where(fn ($query) => $query->where('help_category_id', $categoryId))->ignore($article),
            ],
            'summary' => ['nullable', 'string', 'max:1000'],
            'content' => [$article ? 'sometimes' : 'required', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_featured' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:draft,published'],
        ]);
    }
}
