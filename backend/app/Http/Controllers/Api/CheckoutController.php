<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\GuestAccessService;
use App\Services\MetaConversionsService;
use App\Services\OrderPricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly OrderPricingService $pricing,
        private readonly GuestAccessService $guestAccess,
        private readonly MetaConversionsService $metaConversions
    ) {}

    public function quote(Request $request)
    {
        $quote = $this->pricing->quote($request->validate([
            'product_id' => ['nullable', 'integer'],
            'bundle_id' => ['nullable', 'integer'],
            'slug' => ['nullable', 'string'],
            'landing_page_id' => ['nullable', 'integer'],
            'landing_page_slug' => ['nullable', 'string'],
            'offer_key' => ['nullable', 'string'],
            'coupon_code' => ['nullable', 'string'],
        ]));

        return response()->json(['data' => $this->quotePayload($quote)]);
    }

    public function createOrder(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['nullable', 'integer'],
            'bundle_id' => ['nullable', 'integer'],
            'landing_page_id' => ['nullable', 'integer'],
            'landing_page_slug' => ['nullable', 'string'],
            'offer_key' => ['nullable', 'string'],
            'coupon_code' => ['nullable', 'string'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'payment_method' => ['required', 'string', 'max:40'],
            'tracking_context' => ['nullable', 'array'],
            'tracking_context.fbp' => ['nullable', 'string', 'max:255'],
            'tracking_context.fbc' => ['nullable', 'string', 'max:255'],
            'tracking_context.event_source_url' => ['nullable', 'url', 'max:2048'],
            'tracking_context.landing_page_url' => ['nullable', 'url', 'max:2048'],
            'tracking_context.referrer' => ['nullable', 'string', 'max:2048'],
            'tracking_context.marketing_consent' => ['nullable', 'boolean'],
        ]);

        $quote = $this->pricing->quote($data);
        $item = $quote['item'];

        $guestAccessToken = null;

        $order = DB::transaction(function () use ($request, $data, $quote, $item, &$guestAccessToken) {
            $order = Order::create([
                'uuid' => (string) Str::uuid(),
                'order_number' => $this->nextOrderNumber(),
                'user_id' => $request->user()?->id,
                'customer_name' => $data['customer_name'],
                'customer_email' => strtolower($data['customer_email']),
                'customer_phone' => $data['customer_phone'] ?? null,
                'order_status' => 'pending',
                'payment_status' => 'pending',
                'subtotal_minor' => $quote['subtotal_minor'],
                'discount_minor' => $quote['discount_minor'],
                'total_minor' => $quote['total_minor'],
                'currency' => $quote['currency'],
                'coupon_id' => $quote['coupon']?->id,
                'landing_page_version_id' => $quote['landing_page_version']?->id,
                'metadata' => [
                    'payment_method' => $data['payment_method'],
                    'landing_page_id' => $quote['landing_page']?->id,
                    'offer_key' => $quote['offer_key'],
                    'tracking_context' => $this->trackingContext($request, $data['tracking_context'] ?? []),
                ],
            ]);

            $order->items()->create([
                'purchasable_type' => $quote['type'],
                'purchasable_id' => $item->id,
                'product_id' => $quote['type'] === 'product' ? $item->id : null,
                'bundle_id' => $quote['type'] === 'bundle' ? $item->id : null,
                'product_name' => $item->name,
                'product_slug' => $item->slug,
                'unit_price_minor' => $quote['subtotal_minor'],
                'discount_minor' => $quote['discount_minor'],
                'total_minor' => $quote['total_minor'],
                'currency' => $quote['currency'],
                'snapshot' => $this->quotePayload($quote),
            ]);

            if (! $order->user_id) {
                $guestAccessToken = $this->guestAccess->issue($order);
            }

            return $order->load('items');
        });

        return response()->json(['data' => ['order' => $order, 'guest_access_token' => $guestAccessToken]], 201);
    }

    public function receipt(Request $request, string $orderNumber)
    {
        $order = Order::with('items', 'entitlements.product.files')->where('order_number', $orderNumber)->firstOrFail();

        if ($request->user()) {
            abort_unless((int) $order->user_id === (int) $request->user()->id || $request->user()->roles()->where('name', 'admin')->exists(), 403);
        } else {
            abort_unless($this->guestAccess->resolve($order, $request->query('guest_access_token')), 403);
        }

        return response()->json(['data' => $this->receiptPayload($order)]);
    }

    private function receiptPayload(Order $order): array
    {
        return $order->toArray() + [
            'meta' => [
                'purchase_event_id' => $this->metaConversions->purchaseEventId($order),
                'content_ids' => $this->metaConversions->contentIds($order),
                'content_type' => 'product',
                'num_items' => max(1, (int) $order->items->sum('quantity')),
                'value' => $this->metaConversions->minorToDecimal((int) $order->total_minor, (string) $order->currency),
                'currency' => strtoupper((string) $order->currency),
            ],
        ];
    }

    private function trackingContext(Request $request, array $context): array
    {
        return array_filter([
            'fbp' => is_string($context['fbp'] ?? null) ? $context['fbp'] : null,
            'fbc' => is_string($context['fbc'] ?? null) ? $context['fbc'] : null,
            'event_source_url' => is_string($context['event_source_url'] ?? null) ? $context['event_source_url'] : null,
            'landing_page_url' => is_string($context['landing_page_url'] ?? null) ? $context['landing_page_url'] : null,
            'referrer' => is_string($context['referrer'] ?? null) ? $context['referrer'] : null,
            'marketing_consent' => array_key_exists('marketing_consent', $context) ? (bool) $context['marketing_consent'] : null,
            'client_ip_address' => $request->ip(),
            'client_user_agent' => $request->userAgent(),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function quotePayload(array $quote): array
    {
        return [
            'type' => $quote['type'],
            'id' => $quote['item']->id,
            'title' => $quote['item']->name,
            'slug' => $quote['item']->slug,
            'subtotal_minor' => $quote['subtotal_minor'],
            'discount_minor' => $quote['discount_minor'],
            'total_minor' => $quote['total_minor'],
            'currency' => $quote['currency'],
            'coupon_code' => $quote['coupon']?->code,
            'landing_page_id' => $quote['landing_page']?->id,
            'landing_page_version_id' => $quote['landing_page_version']?->id,
            'offer_key' => $quote['offer_key'],
        ];
    }

    private function nextOrderNumber(): string
    {
        $count = Order::whereYear('created_at', now()->year)->lockForUpdate()->count() + 1;

        return sprintf('LBLX-%s-%06d', now()->year, $count);
    }
}
