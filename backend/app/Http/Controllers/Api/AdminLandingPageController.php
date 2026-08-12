<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LandingPage;
use App\Models\LandingPageVersion;
use App\Services\LandingPageEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class AdminLandingPageController extends Controller
{
    public function __construct(private readonly LandingPageEngine $engine) {}

    public function index()
    {
        $pages = LandingPage::with('primaryProduct', 'publishedVersion')->latest()->paginate(20);

        return response()->json(['data' => $pages->through(fn ($page) => $this->pagePayload($page))]);
    }

    public function show(LandingPage $landingPage)
    {
        return response()->json(['data' => $this->pagePayload($landingPage->load('primaryProduct', 'publishedVersion', 'versions', 'offers.product', 'offers.bundle'), true)]);
    }

    public function upload(Request $request)
    {
        $data = $request->validate([
            'package' => ['required', 'file', 'max:51200'],
            'name' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/\A[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*\z/'],
            'primary_product_id' => ['nullable', 'integer', 'exists:products,id'],
            'offers' => ['nullable', 'array'],
        ]);

        $version = $this->engine->upload($data['package'], $data, $request->user()->id);

        return response()->json(['data' => $this->versionPayload($version->load('landingPage'))], 201);
    }

    public function assignOffers(Request $request, LandingPage $landingPage)
    {
        $data = $request->validate([
            'primary_product_id' => ['nullable', 'integer', 'exists:products,id'],
            'offers' => ['required', 'array', 'min:1'],
            'offers.*.offer_key' => ['required', 'string', 'max:80'],
            'offers.*.offer_type' => ['required', 'in:product,bundle'],
            'offers.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'offers.*.bundle_id' => ['nullable', 'integer', 'exists:bundles,id'],
            'offers.*.is_primary' => ['nullable', 'boolean'],
        ]);

        $landingPage->forceFill(['primary_product_id' => $data['primary_product_id'] ?? $landingPage->primary_product_id])->save();
        $this->engine->syncOffers($landingPage, $landingPage->publishedVersion, $data['offers']);

        return response()->json(['data' => $this->pagePayload($landingPage->fresh('offers.product', 'offers.bundle'), true)]);
    }

    public function publish(LandingPage $landingPage, LandingPageVersion $version)
    {
        return response()->json(['data' => $this->pagePayload($this->engine->publish($landingPage, $version))]);
    }

    public function unpublish(LandingPage $landingPage)
    {
        return response()->json(['data' => $this->pagePayload($this->engine->unpublish($landingPage))]);
    }

    public function previewUrl(LandingPageVersion $version)
    {
        return response()->json(['data' => [
            'url' => URL::temporarySignedRoute('landing.preview', now()->addMinutes(30), ['version' => $version->id]),
        ]]);
    }

    public function download(LandingPageVersion $version)
    {
        return response()->download($this->engine->sourceDownload($version), 'landing-page-v'.$version->version_number.'.zip');
    }

    public function analytics(LandingPage $landingPage)
    {
        $events = DB::table('analytics_events')->where('landing_page_id', $landingPage->id);
        $visitors = (clone $events)->where('event_name', 'landing_page_view')->distinct('visitor_key_hash')->count('visitor_key_hash');
        $cta = (clone $events)->where('event_name', 'cta_click')->count();
        $checkouts = (clone $events)->where('event_name', 'checkout_started')->count();
        $purchases = (clone $events)->where('event_name', 'purchase')->count();
        $purchaseRows = (clone $events)->where('event_name', 'purchase')->get('properties');
        $revenue = $purchaseRows->sum(fn ($row) => (int) ((json_decode($row->properties ?? '{}', true)['total_minor'] ?? 0)));

        return response()->json(['data' => [
            'visitors' => $visitors,
            'cta_clicks' => $cta,
            'checkout_started' => $checkouts,
            'purchases' => $purchases,
            'conversion_rate' => $visitors ? round($purchases / $visitors * 100, 2) : 0,
            'revenue_minor' => (int) $revenue,
            'aov_minor' => $purchases ? (int) floor($revenue / $purchases) : 0,
        ]]);
    }

    private function pagePayload(LandingPage $page, bool $detail = false): array
    {
        $payload = [
            'id' => $page->id,
            'uuid' => $page->uuid,
            'name' => $page->name,
            'slug' => $page->slug,
            'status' => $page->status,
            'product' => $page->primaryProduct?->name,
            'primary_product_id' => $page->primary_product_id,
            'published_version_id' => $page->published_version_id,
            'version' => $page->publishedVersion ? 'v'.$page->publishedVersion->version_number : null,
            'updated_at' => $page->updated_at,
        ];

        if ($detail) {
            $payload['versions'] = $page->versions->sortByDesc('version_number')->values()->map(fn ($version) => $this->versionPayload($version));
            $payload['offers'] = $page->offers->map(fn ($offer) => [
                'offer_key' => $offer->offer_key,
                'offer_type' => $offer->offer_type,
                'product_id' => $offer->product_id,
                'bundle_id' => $offer->bundle_id,
                'label' => $offer->product?->name ?? $offer->bundle?->name,
                'is_primary' => $offer->is_primary,
            ]);
        }

        return $payload;
    }

    private function versionPayload(LandingPageVersion $version): array
    {
        return [
            'id' => $version->id,
            'version_number' => $version->version_number,
            'status' => $version->status,
            'sdk_version' => $version->sdk_version,
            'package_size_bytes' => $version->package_size_bytes,
            'validation_report' => $version->validation_report,
            'created_at' => $version->created_at,
            'published_at' => $version->published_at,
            'landing_page_id' => $version->landing_page_id,
        ];
    }
}
