<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GuestAccessToken;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\ProductFile;
use App\Services\DownloadDeliveryService;
use App\Services\GuestAccessService;
use App\Services\MetaConversionsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function __construct(
        private readonly DownloadDeliveryService $downloads,
        private readonly GuestAccessService $guestAccess,
        private readonly MetaConversionsService $metaConversions
    ) {}

    public function overview(Request $request)
    {
        $orders = $request->user()->orders()->with('items')->latest()->limit(3)->get();
        $library = $request->user()->entitlements()->with('product.files')->where('status', 'active')->latest()->limit(3)->get();

        return response()->json(['data' => [
            'customer' => $this->userPayload($request->user()),
            'purchased_product_count' => $request->user()->entitlements()->where('status', 'active')->distinct('product_id')->count('product_id'),
            'recent_orders' => $orders->map(fn (Order $order) => $this->orderPayload($order)),
            'recent_library_items' => $library->map(fn (Entitlement $entitlement) => $this->libraryPayload($entitlement)),
        ]]);
    }

    public function library(Request $request)
    {
        return response()->json([
            'data' => $request->user()->entitlements()->with('product.files')->where('status', 'active')->latest()->get()
                ->map(fn (Entitlement $entitlement) => $this->libraryPayload($entitlement)),
        ]);
    }

    public function libraryDetail(Request $request, int $productId)
    {
        $entitlement = Entitlement::with('product.files', 'order')
            ->where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->first();

        abort_unless($entitlement, 403);

        return response()->json(['data' => $this->libraryPayload($entitlement, true)]);
    }

    public function orders(Request $request)
    {
        $orders = $request->user()->orders()->with('items')->latest()->paginate(10);

        return response()->json([
            'data' => $orders->through(fn (Order $order) => $this->orderPayload($order))->items(),
            'meta' => ['current_page' => $orders->currentPage(), 'last_page' => $orders->lastPage(), 'total' => $orders->total()],
        ]);
    }

    public function orderDetail(Request $request, string $orderNumber)
    {
        $order = $request->user()->orders()->with('items', 'entitlements.product')->where('order_number', $orderNumber)->first();

        abort_unless($order, 403);

        return response()->json(['data' => $this->orderPayload($order, true)]);
    }

    public function downloads(Request $request)
    {
        $entitlements = $request->user()->entitlements()->with('product.files')->where('status', 'active')->get();

        return response()->json(['data' => $entitlements->flatMap(function (Entitlement $entitlement) {
            return $entitlement->product->files
                ->where('status', 'active')
                ->map(fn (ProductFile $file) => $this->filePayload($file, $entitlement));
        })->values()]);
    }

    public function profile(Request $request)
    {
        return response()->json(['data' => $request->user()->load('roles')]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $request->user()->update($data);

        return response()->json(['data' => $request->user()]);
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->forceFill(['password' => Hash::make($data['password'])])->save();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function download(Request $request, ProductFile $file)
    {
        $entitlement = Entitlement::where('user_id', $request->user()->id)
            ->where('product_id', $file->product_id)
            ->where('status', 'active')
            ->first();

        abort_unless($entitlement, 403);
        $this->downloads->ensureDownloadable($file, $entitlement);

        return response()->json([
            'data' => [
                'file' => $this->filePayload($file, $entitlement),
            ] + $this->downloads->signedCustomerUrl($file, $entitlement),
        ]);
    }

    public function serveCustomerDownload(Request $request, ProductFile $file, Entitlement $entitlement)
    {
        $signatureValid = $request->hasValidSignature(false);

        if (! $signatureValid) {
            Log::warning('Customer download rejected: invalid relative signature.', [
                'file_id' => $file->id,
                'entitlement_id' => $entitlement->id,
                'signature_valid' => false,
            ]);
        }

        abort_unless($signatureValid, 403);
        abort_unless($request->user() && (int) $entitlement->user_id === (int) $request->user()->id, 403);
        $this->downloads->ensureDownloadable($file, $entitlement);
        $this->downloads->record($request, $file, $entitlement);

        abort_unless(Storage::disk($file->storage_disk)->exists($file->storage_path), 404);

        return Storage::disk($file->storage_disk)->download($file->storage_path, $file->name);
    }

    public function guestOrder(Request $request, string $orderNumber)
    {
        $order = Order::with('items', 'entitlements.product.files')->where('order_number', $orderNumber)->firstOrFail();
        $token = $this->guestAccess->resolve($order, $request->query('guest_access_token'));

        abort_unless($token && $order->payment_status === 'paid', 403);
        $token->forceFill(['last_used_at' => now()])->save();

        return response()->json([
            'data' => [
                'order' => $this->orderPayload($order, true),
                'downloads' => $order->entitlements->flatMap(function (Entitlement $entitlement) use ($request) {
                    return $entitlement->product->files->where('status', 'active')->map(function (ProductFile $file) use ($entitlement, $request) {
                        return $this->filePayload($file, $entitlement) + $this->downloads->signedGuestUrl($file, $entitlement, (string) $request->query('guest_access_token'));
                    });
                })->values(),
            ],
        ]);
    }

    public function serveGuestDownload(Request $request, ProductFile $file, Entitlement $entitlement)
    {
        $signatureValid = $request->hasValidSignature(false);

        if (! $signatureValid) {
            Log::warning('Guest download rejected: invalid relative signature.', [
                'file_id' => $file->id,
                'entitlement_id' => $entitlement->id,
                'order_id' => $entitlement->order_id,
                'signature_valid' => false,
                'guest_token_present' => is_string($request->query('token')) && $request->query('token') !== '',
            ]);
        }

        abort_unless($signatureValid, 403);
        $order = $entitlement->order;
        $token = $request->query('token');
        $guestToken = $this->guestAccess->resolve($order, is_string($token) ? $token : null);
        $guestAccessValid = (bool) $guestToken;

        if (! $guestAccessValid || $order->payment_status !== 'paid') {
            Log::warning('Guest download rejected: authorization failed.', [
                'file_id' => $file->id,
                'entitlement_id' => $entitlement->id,
                'order_id' => $order->id,
                'signature_valid' => true,
                'guest_token_present' => is_string($token) && $token !== '',
                'guest_access_valid' => $guestAccessValid,
                'payment_status' => $order->payment_status,
            ]);
        }

        abort_unless($guestAccessValid && $order->payment_status === 'paid', 403);
        $this->downloads->ensureDownloadable($file, $entitlement);
        $this->downloads->record($request, $file, $entitlement);

        abort_unless(Storage::disk($file->storage_disk)->exists($file->storage_path), 404);

        return Storage::disk($file->storage_disk)->download($file->storage_path, $file->name);
    }

    private function userPayload($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'email_verified' => (bool) $user->email_verified_at,
            'roles' => $user->roles->pluck('name')->values(),
        ];
    }

    private function libraryPayload(Entitlement $entitlement, bool $detail = false): array
    {
        $product = $entitlement->product;

        return [
            'entitlement_id' => $entitlement->id,
            'product_id' => $product->id,
            'product_uuid' => $product->uuid,
            'slug' => $product->slug,
            'title' => $product->name,
            'cover' => $product->cover_image_path,
            'description' => $product->short_description,
            'purchased_at' => $entitlement->granted_at?->toDateString(),
            'resource_count' => $product->files->where('status', 'active')->count(),
            'files' => $detail ? $product->files->where('status', 'active')->map(fn (ProductFile $file) => $this->filePayload($file, $entitlement))->values() : [],
        ];
    }

    private function orderPayload(Order $order, bool $detail = false): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'date' => $order->created_at?->toDateString(),
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'subtotal_minor' => $order->subtotal_minor,
            'discount_minor' => $order->discount_minor,
            'total_minor' => $order->total_minor,
            'currency' => $order->currency,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->product_name,
                'slug' => $item->product_slug,
                'quantity' => $item->quantity,
                'unit_price_minor' => $item->unit_price_minor,
                'discount_minor' => $item->discount_minor,
                'total_minor' => $item->total_minor,
                'currency' => $item->currency,
                'snapshot' => $detail ? $item->snapshot : null,
            ]),
            'meta' => $order->payment_status === 'paid' ? [
                'purchase_event_id' => $this->metaConversions->purchaseEventId($order),
                'content_ids' => $this->metaConversions->contentIds($order),
                'content_type' => 'product',
                'num_items' => max(1, (int) $order->items->sum('quantity')),
                'value' => $this->metaConversions->minorToDecimal((int) $order->total_minor, (string) $order->currency),
                'currency' => strtoupper((string) $order->currency),
            ] : null,
        ];
    }

    private function filePayload(ProductFile $file, Entitlement $entitlement): array
    {
        return [
            'file_id' => $file->id,
            'product_id' => $file->product_id,
            'entitlement_id' => $entitlement->id,
            'product_title' => $entitlement->product?->name,
            'name' => $file->name,
            'file_type' => $file->file_type,
            'file_size_bytes' => $file->file_size_bytes,
            'version' => $file->version,
            'status' => $file->status,
        ];
    }
}
