<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FaqItem;
use Illuminate\Http\Request;

class AdminFaqItemController extends Controller
{
    public function index(Request $request)
    {
        $items = FaqItem::with('category')
            ->when($request->query('category_id'), fn ($query, $id) => $query->where('faq_category_id', $id))
            ->orderBy('sort_order')
            ->orderBy('question')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request)
    {
        $item = FaqItem::create($this->data($request));

        return response()->json(['data' => $item->load('category')], 201);
    }

    public function update(Request $request, FaqItem $faqItem)
    {
        $faqItem->update($this->data($request, true));

        return response()->json(['data' => $faqItem->fresh('category')]);
    }

    public function destroy(FaqItem $faqItem)
    {
        $faqItem->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    private function data(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'faq_category_id' => [$updating ? 'sometimes' : 'required', 'integer', 'exists:faq_categories,id'],
            'question' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'answer' => [$updating ? 'sometimes' : 'required', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
    }
}
