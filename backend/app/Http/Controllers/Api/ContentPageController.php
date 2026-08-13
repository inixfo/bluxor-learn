<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;

class ContentPageController extends Controller
{
    public function show(string $slug)
    {
        $page = ContentPage::where('slug', $slug)->where('status', 'published')->firstOrFail();

        return response()->json(['data' => $page]);
    }
}
