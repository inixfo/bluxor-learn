<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LandingPage;
use App\Models\LandingPageVersion;
use App\Models\Order;
use App\Models\Product;
use App\Services\LandingPageEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

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

        if (! empty($data['primary_product_id'])) {
            $this->publishedProductOrFail((int) $data['primary_product_id'], 'primary_product_id');
        }

        $landingPage->forceFill(['primary_product_id' => $data['primary_product_id'] ?? $landingPage->primary_product_id])->save();
        $this->engine->syncOffers($landingPage, $landingPage->publishedVersion, $data['offers']);

        return response()->json(['data' => $this->pagePayload($landingPage->fresh('offers.product', 'offers.bundle'), true)]);
    }

    public function updateProduct(Request $request, LandingPage $landingPage)
    {
        $data = $request->validate([
            'primary_product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $productId = (int) $data['primary_product_id'];
        $this->publishedProductOrFail($productId, 'primary_product_id');

        return DB::transaction(function () use ($landingPage, $productId) {
            $landingPage->forceFill(['primary_product_id' => $productId])->save();

            $primaryOffer = $landingPage->offers()->where('offer_type', 'product')->where('is_primary', true)->first()
                ?: $landingPage->offers()->where('offer_type', 'product')->orderBy('sort_order')->first();

            if ($primaryOffer) {
                $primaryOffer->forceFill([
                    'product_id' => $productId,
                    'bundle_id' => null,
                    'is_primary' => true,
                ])->save();
                $landingPage->offers()->whereKeyNot($primaryOffer->id)->update(['is_primary' => false]);
            } elseif (! $landingPage->offers()->exists()) {
                $landingPage->offers()->create([
                    'landing_page_version_id' => $landingPage->published_version_id,
                    'offer_key' => 'single',
                    'offer_type' => 'product',
                    'product_id' => $productId,
                    'bundle_id' => null,
                    'sort_order' => 0,
                    'is_primary' => true,
                ]);
            }

            return response()->json(['data' => $this->pagePayload($landingPage->fresh('primaryProduct', 'offers.product', 'offers.bundle'), true)]);
        });
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

    public function analytics(Request $request, LandingPage $landingPage)
    {
        [$start, $end, $range] = $this->analyticsWindow($request);

        $events = DB::table('analytics_events')
            ->where('landing_page_id', $landingPage->id)
            ->whereBetween('occurred_at', [$start, $end]);
        $viewEvents = (clone $events)->where('event_name', 'landing_page_view');
        $eventRows = (clone $events)->get();
        $viewRows = $eventRows->where('event_name', 'landing_page_view');
        $orders = $this->attributedOrders($landingPage, $start, $end);
        $paidOrders = $orders->where('payment_status', 'paid');

        $visitors = (clone $viewEvents)->whereNotNull('visitor_key_hash')->distinct('visitor_key_hash')->count('visitor_key_hash');
        $sessions = (clone $viewEvents)->whereNotNull('session_key_hash')->distinct('session_key_hash')->count('session_key_hash');
        $pageViews = (clone $viewEvents)->count();
        $cta = $eventRows->where('event_name', 'cta_click')->count();
        $checkouts = $eventRows->where('event_name', 'checkout_started')->count();
        $paidCount = $paidOrders->count();
        $revenue = (int) $paidOrders->sum('total_minor');

        return response()->json(['data' => [
            'range' => $range,
            'from' => $start->toISOString(),
            'to' => $end->toISOString(),
            'visitors' => $visitors,
            'sessions' => $sessions,
            'page_views' => $pageViews,
            'orders' => $orders->count(),
            'paid_orders' => $paidCount,
            'cta_clicks' => $cta,
            'checkout_started' => $checkouts,
            'purchases' => $paidCount,
            'conversion_rate' => $visitors ? round($paidCount / $visitors * 100, 2) : 0,
            'revenue_minor' => $revenue,
            'aov_minor' => $paidCount ? (int) floor($revenue / $paidCount) : 0,
            'source_breakdown' => $this->sourceBreakdown($viewRows, $orders),
            'recent_conversions' => $this->recentConversions($paidOrders),
        ]]);
    }

    private function analyticsWindow(Request $request): array
    {
        $data = $request->validate([
            'range' => ['nullable', 'in:today,yesterday,7d,30d,custom'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $range = $data['range'] ?? '30d';
        $now = now();

        if ($range === 'today') {
            return [$now->copy()->startOfDay(), $now->copy()->endOfDay(), $range];
        }

        if ($range === 'yesterday') {
            return [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay(), $range];
        }

        if ($range === '7d') {
            return [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay(), $range];
        }

        if ($range === 'custom' && ! empty($data['from']) && ! empty($data['to'])) {
            return [Carbon::parse($data['from'])->startOfDay(), Carbon::parse($data['to'])->endOfDay(), $range];
        }

        return [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay(), '30d'];
    }

    private function attributedOrders(LandingPage $landingPage, Carbon $start, Carbon $end): Collection
    {
        $versionIds = $landingPage->versions()->pluck('id')->map(fn ($id) => (int) $id)->all();

        return Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->latest()
            ->get()
            ->filter(function (Order $order) use ($landingPage, $versionIds) {
                $metadata = $order->metadata ?: [];
                $attribution = is_array($metadata['order_attribution'] ?? null) ? $metadata['order_attribution'] : [];

                return (int) ($metadata['landing_page_id'] ?? 0) === (int) $landingPage->id
                    || (int) ($attribution['landing_page_id'] ?? 0) === (int) $landingPage->id
                    || in_array((int) $order->landing_page_version_id, $versionIds, true)
                    || in_array((int) ($attribution['landing_page_version_id'] ?? 0), $versionIds, true);
            })
            ->values();
    }

    private function sourceBreakdown(Collection $viewRows, Collection $orders): array
    {
        $rows = [];

        foreach ($viewRows as $event) {
            $key = $this->sourceKey($event->source ?: 'Direct', $event->medium ?: 'direct', $event->campaign ?: null);
            $rows[$key] ??= $this->emptySourceRow($event->source ?: 'Direct', $event->medium ?: 'direct', $event->campaign ?: null);
            if ($event->visitor_key_hash) {
                $rows[$key]['visitor_keys'][$event->visitor_key_hash] = true;
            }
            if ($event->session_key_hash) {
                $rows[$key]['session_keys'][$event->session_key_hash] = true;
            }
        }

        foreach ($orders as $order) {
            $touch = $this->orderTouch($order);
            $key = $this->sourceKey($touch['source'], $touch['medium'], $touch['campaign']);
            $rows[$key] ??= $this->emptySourceRow($touch['source'], $touch['medium'], $touch['campaign']);
            $rows[$key]['orders']++;
            if ($order->payment_status === 'paid') {
                $rows[$key]['paid_orders']++;
                $rows[$key]['revenue_minor'] += (int) $order->total_minor;
            }
        }

        return collect($rows)->map(function (array $row) {
            $visitors = count($row['visitor_keys']);
            $paidOrders = (int) $row['paid_orders'];
            $sessions = count($row['session_keys']);

            unset($row['visitor_keys'], $row['session_keys']);
            $row['visitors'] = $visitors;
            $row['sessions'] = $sessions;
            $row['conversion_rate'] = $visitors ? round($paidOrders / $visitors * 100, 2) : 0;

            return $row;
        })->sortByDesc('revenue_minor')->values()->all();
    }

    private function recentConversions(Collection $paidOrders): array
    {
        return $paidOrders->sortByDesc('created_at')->take(10)->map(function (Order $order) {
            $touch = $this->orderTouch($order);

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_email' => $order->customer_email,
                'customer_name' => $order->customer_name,
                'created_at' => $order->created_at,
                'amount_minor' => $order->total_minor,
                'currency' => $order->currency,
                'source' => $touch['source'],
                'medium' => $touch['medium'],
                'campaign' => $touch['campaign'],
            ];
        })->values()->all();
    }

    private function emptySourceRow(string $source, string $medium, ?string $campaign): array
    {
        return [
            'source' => $source,
            'medium' => $medium,
            'campaign' => $campaign,
            'visitor_keys' => [],
            'session_keys' => [],
            'orders' => 0,
            'paid_orders' => 0,
            'revenue_minor' => 0,
        ];
    }

    private function orderTouch(Order $order): array
    {
        $metadata = $order->metadata ?: [];
        $attribution = is_array($metadata['order_attribution'] ?? null) ? $metadata['order_attribution'] : [];
        $touch = is_array($attribution['last_touch'] ?? null) ? $attribution['last_touch'] : ($attribution['first_touch'] ?? []);

        return [
            'source' => (string) ($touch['source'] ?? 'Direct'),
            'medium' => (string) ($touch['medium'] ?? 'direct'),
            'campaign' => $touch['campaign'] ?? null,
        ];
    }

    private function sourceKey(string $source, string $medium, ?string $campaign): string
    {
        return strtolower($source).'|'.strtolower($medium).'|'.strtolower((string) $campaign);
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

    private function publishedProductOrFail(int $productId, string $field): Product
    {
        $product = Product::whereKey($productId)->where('status', 'published')->first();

        if (! $product) {
            throw ValidationException::withMessages([$field => ['Product is not available.']]);
        }

        return $product;
    }
}
