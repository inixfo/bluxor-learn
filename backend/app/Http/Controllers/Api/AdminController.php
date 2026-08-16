<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\ContactInquiry;
use App\Models\Coupon;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LandingPagePackageValidator;
use App\Services\PublicMediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function dashboard()
    {
        $paidRevenue = (int) Order::where('payment_status', 'paid')->sum('total_minor');
        $refundedRevenue = (int) Order::where('payment_status', 'refunded')->sum('total_minor');
        $customers = DB::query()
            ->fromSub(Order::selectRaw('lower(customer_email) as email')->distinct(), 'order_customers')
            ->count();

        return response()->json(['data' => [
            'metrics' => [
                'revenue_minor' => $paidRevenue - $refundedRevenue,
                'orders' => Order::count(),
                'customers' => $customers,
                'products' => Product::count(),
                'new_support_messages' => ContactInquiry::where('status', 'new')->count(),
                'unresolved_inquiries' => ContactInquiry::whereIn('status', ['new', 'read', 'replied'])->count(),
            ],
            'recent_orders' => Order::with('items', 'paymentTransactions')->latest()->limit(5)->get(),
            'top_products' => Product::query()
                ->addSelect([
                    'paid_revenue_minor' => DB::table('order_items')
                        ->join('orders', 'orders.id', '=', 'order_items.order_id')
                        ->selectRaw('coalesce(sum(order_items.total_minor), 0)')
                        ->whereColumn('order_items.product_id', 'products.id')
                        ->where('orders.payment_status', 'paid'),
                ])
                ->orderByDesc('paid_revenue_minor')
                ->orderByDesc('products.featured')
                ->limit(4)
                ->get(),
        ]]);
    }

    public function products(Request $request)
    {
        $status = $request->query('status');
        $products = Product::query()
            ->with('category')
            ->when($status === 'deleted', fn ($query) => $query->onlyTrashed())
            ->when($request->query('q'), fn ($query, $q) => $query->where('name', 'like', "%{$q}%"))
            ->when($status && $status !== 'deleted', fn ($query) => $query->where('status', $status))
            ->when($request->query('type'), fn ($query, $type) => $query->where('product_type', $type))
            ->latest()
            ->paginate(20);

        return response()->json(['data' => $products]);
    }

    public function showProduct(Product $product)
    {
        return response()->json(['data' => $product->load('category', 'files', 'tags', 'resources')]);
    }

    public function storeProduct(Request $request, PublicMediaService $media)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:products,slug'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'product_type' => ['required', 'string'],
            'regular_price_minor' => ['required', 'integer', 'min:0'],
            'sale_price_minor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'status' => ['required', 'in:draft,published,archived'],
            'short_description' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'cover_image_path' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        unset($data['cover_image']);
        if ($request->hasFile('cover_image')) {
            $data['cover_image_path'] = $media->storeImage($request->file('cover_image'), 'product-images');
        }

        $product = Product::create(['uuid' => (string) Str::uuid(), 'published_at' => $data['status'] === 'published' ? now() : null] + $data);
        $this->audit->log('product.created', $product, ['after' => $product->only(array_keys($data))], $request);

        return response()->json(['data' => $product], 201);
    }

    public function updateProduct(Request $request, Product $product, PublicMediaService $media)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:products,slug,'.$product->id],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'product_type' => ['sometimes', 'string'],
            'regular_price_minor' => ['sometimes', 'integer', 'min:0'],
            'sale_price_minor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'status' => ['sometimes', 'in:draft,published,archived'],
            'short_description' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'cover_image_path' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_cover_image' => ['nullable', 'boolean'],
        ]);

        $oldCover = $product->cover_image_path;
        unset($data['cover_image']);
        if ($request->boolean('remove_cover_image')) {
            $data['cover_image_path'] = null;
        }

        if ($request->hasFile('cover_image')) {
            $data['cover_image_path'] = $media->storeImage($request->file('cover_image'), 'product-images');
        }

        $before = $product->only(array_keys($data));

        if (($data['status'] ?? null) === 'published' && ! $product->published_at) {
            $data['published_at'] = now();
        }

        $product->update($data);
        if (array_key_exists('cover_image_path', $data) && $oldCover !== ($data['cover_image_path'] ?? null)) {
            $media->deleteIfManaged($oldCover);
        }

        $this->audit->log('product.updated', $product, ['before' => $before, 'after' => $product->fresh()->only(array_keys($data))], $request);

        return response()->json(['data' => $product->fresh('category')]);
    }

    public function publishProduct(Product $product)
    {
        $product->forceFill([
            'status' => 'published',
            'published_at' => $product->published_at ?: now(),
        ])->save();
        $this->audit->log('product.published', $product);

        return response()->json(['data' => $product->fresh('category')]);
    }

    public function archiveProduct(Product $product)
    {
        $product->forceFill(['status' => 'archived'])->save();
        $this->audit->log('product.archived', $product);

        return response()->json(['data' => $product->fresh('category')]);
    }

    public function restoreProduct(Product $product)
    {
        $product->forceFill([
            'status' => 'draft',
            'published_at' => null,
        ])->save();
        $this->audit->log('product.restored', $product);

        return response()->json(['data' => $product->fresh('category')]);
    }

    public function deleteProduct(Request $request, Product $product)
    {
        $activeBundleCount = $product->bundles()->where('bundles.status', 'published')->count();
        abort_if($activeBundleCount > 0, 422, 'Remove this product from '.$activeBundleCount.' active bundle'.($activeBundleCount === 1 ? '' : 's').' before deleting it.');

        $landingCount = LandingPage::query()
            ->where('status', 'published')
            ->where(fn ($query) => $query
                ->where('primary_product_id', $product->id)
                ->orWhereHas('offers', fn ($offer) => $offer->where('offer_type', 'product')->where('product_id', $product->id)))
            ->count();

        $metadata = [
            'product_id' => $product->id,
            'product_title' => $product->name,
            'active_landing_pages' => $landingCount,
            'resources_count' => $product->resources()->count(),
        ];

        $product->forceFill(['status' => 'archived'])->save();
        $product->delete();

        $this->audit->log('product.deleted', $product, $metadata, $request);

        return response()->json(['data' => ['ok' => true, 'active_landing_pages' => $landingCount]]);
    }

    public function restoreDeletedProduct(Request $request, int $id)
    {
        $product = Product::withTrashed()->findOrFail($id);
        abort_unless($product->trashed(), 422, 'Product is not deleted.');

        $product->restore();
        $product->forceFill([
            'status' => 'draft',
            'published_at' => null,
        ])->save();

        $this->audit->log('product.restored_from_trash', $product, ['product_id' => $product->id, 'product_title' => $product->name], $request);

        return response()->json(['data' => $product->fresh('category')]);
    }

    public function uploadProductFile(Request $request, Product $product)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:512000'],
            'name' => ['nullable', 'string', 'max:255'],
            'file_type' => ['nullable', 'string', 'max:50'],
            'version' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $upload = $data['file'];
        $storedName = Str::uuid().'-'.Str::slug(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME));
        $extension = $upload->getClientOriginalExtension();
        $path = $upload->storeAs(
            'products/'.$product->slug,
            $storedName.($extension ? '.'.$extension : ''),
            'private'
        );

        $file = ProductFile::create([
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'name' => $data['name'] ?? $upload->getClientOriginalName(),
            'file_type' => $data['file_type'] ?? strtoupper($extension ?: 'FILE'),
            'file_size_bytes' => $upload->getSize(),
            'storage_disk' => 'private',
            'storage_path' => $path,
            'version' => $data['version'] ?? '1.0.0',
            'status' => $data['status'] ?? 'active',
        ]);
        $this->audit->log('product_file.uploaded', $file, ['product_id' => $product->id, 'name' => $file->name, 'size' => $file->file_size_bytes], $request);

        return response()->json(['data' => $file], 201);
    }

    public function orders()
    {
        $orders = Order::with('items', 'entitlements', 'paymentTransactions')->latest()->paginate(20);
        $orders->getCollection()->transform(function (Order $order) {
            $order->payment_gateway = $order->paymentTransactions->first()?->gateway;

            return $order;
        });

        return response()->json(['data' => $orders]);
    }

    public function customers()
    {
        $users = User::with('roles')->get()->keyBy(fn ($user) => strtolower($user->email));
        $orders = Order::with('items')->get()->groupBy(fn ($order) => strtolower($order->customer_email));

        $rows = collect($users->keys())->merge($orders->keys())->unique()->map(function ($email) use ($users, $orders) {
            $user = $users->get($email);
            $customerOrders = $orders->get($email, collect());
            $paidOrders = $customerOrders->where('payment_status', 'paid');
            $refundedOrders = $customerOrders->where('payment_status', 'refunded');
            $firstOrder = $customerOrders->sortBy('created_at')->first();
            $lastOrder = $customerOrders->sortByDesc('created_at')->first();
            $productNames = $customerOrders->flatMap(fn ($order) => $order->items->pluck('product_name'))->unique()->values();
            $paidRevenue = (int) $paidOrders->sum('total_minor');
            $refundedAmount = (int) $refundedOrders->sum('total_minor');

            return [
                'id' => $user?->id ?: crc32($email),
                'name' => $user?->name ?: ($lastOrder?->customer_name ?: 'Guest Customer'),
                'email' => $email,
                'account_status' => $user ? $user->status : 'guest',
                'verified' => (bool) $user?->email_verified_at,
                'orders_count' => $customerOrders->count(),
                'products_count' => $productNames->count(),
                'products' => $productNames->all(),
                'paid_revenue_minor' => $paidRevenue,
                'refunded_amount_minor' => $refundedAmount,
                'net_revenue_minor' => $paidRevenue - $refundedAmount,
                'ltv_minor' => $paidRevenue - $refundedAmount,
                'first_purchase_at' => $firstOrder?->created_at,
                'last_purchase_at' => $lastOrder?->created_at,
                'created_at' => $user?->created_at ?: $firstOrder?->created_at,
                'updated_at' => $user?->updated_at ?: $lastOrder?->updated_at,
                'roles' => $user?->roles ?? [],
            ];
        })->sortByDesc('last_purchase_at')->values();

        return response()->json(['data' => ['data' => $rows, 'total' => $rows->count()]]);
    }

    public function offerItems(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['product', 'bundle'])],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $model = $data['type'] === 'bundle' ? Bundle::query() : Product::query();
        $items = $model
            ->where('status', 'published')
            ->when($data['q'] ?? null, fn ($query, $q) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'slug' => $item->slug,
                'type' => $data['type'],
                'price_minor' => $data['type'] === 'bundle'
                    ? ($item->sale_price_minor ?? $item->bundle_price_minor)
                    : ($item->sale_price_minor ?? $item->regular_price_minor),
                'currency' => $item->currency,
            ]);

        return response()->json(['data' => $items]);
    }

    public function coupons()
    {
        return response()->json(['data' => Coupon::with('products:id,name', 'bundles:id,name')->latest()->paginate(20)]);
    }

    public function showCoupon(Coupon $coupon)
    {
        return response()->json(['data' => $coupon->load('products:id,name', 'bundles:id,name')]);
    }

    public function storeCoupon(Request $request)
    {
        $data = $this->couponData($request);
        $coupon = Coupon::create($data['attributes']);
        $coupon->products()->sync($data['product_ids']);
        $coupon->bundles()->sync($data['bundle_ids']);
        $this->audit->log('coupon.created', $coupon, ['after' => $coupon->load('products', 'bundles')->toArray()], $request);

        return response()->json(['data' => $coupon->load('products:id,name', 'bundles:id,name')], 201);
    }

    public function updateCoupon(Request $request, Coupon $coupon)
    {
        $data = $this->couponData($request, true);
        $before = $coupon->load('products', 'bundles')->toArray();
        $coupon->update($data['attributes']);
        $coupon->products()->sync($data['product_ids']);
        $coupon->bundles()->sync($data['bundle_ids']);
        $this->audit->log('coupon.updated', $coupon, ['before' => $before, 'after' => $coupon->fresh('products', 'bundles')->toArray()], $request);

        return response()->json(['data' => $coupon->load('products:id,name', 'bundles:id,name')]);
    }

    public function pauseCoupon(Coupon $coupon)
    {
        $coupon->forceFill(['status' => 'paused'])->save();
        $this->audit->log('coupon.paused', $coupon);

        return response()->json(['data' => $coupon]);
    }

    public function archiveCoupon(Coupon $coupon)
    {
        $coupon->forceFill(['status' => 'archived'])->save();
        $this->audit->log('coupon.archived', $coupon);

        return response()->json(['data' => $coupon]);
    }

    public function analytics()
    {
        $paidRevenue = (int) Order::where('payment_status', 'paid')->sum('total_minor');
        $refundedRevenue = (int) Order::where('payment_status', 'refunded')->sum('total_minor');
        $orders = Order::whereIn('payment_status', ['paid', 'refunded'])->get();

        return response()->json(['data' => [
            'summary' => [
                'revenue_minor' => $paidRevenue - $refundedRevenue,
                'paid_revenue_minor' => $paidRevenue,
                'refunded_amount_minor' => $refundedRevenue,
                'ltv_minor' => $orders->groupBy(fn ($order) => strtolower($order->customer_email))->sum(function ($customerOrders) {
                    return (int) $customerOrders->where('payment_status', 'paid')->sum('total_minor')
                        - (int) $customerOrders->where('payment_status', 'refunded')->sum('total_minor');
                }),
                'purchases' => DB::table('analytics_events')->where('event_name', 'purchase')->count(),
                'visitors' => DB::table('analytics_events')->whereIn('event_name', ['page_view', 'landing_page_view'])->distinct('visitor_key_hash')->count('visitor_key_hash'),
            ],
            'landing_pages' => LandingPage::with('versions')->get(),
            'products' => Product::all(),
        ]]);
    }

    public function auditLogs(Request $request)
    {
        $data = $request->validate([
            'actor' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:120'],
            'entity' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $logs = AuditLog::with('actor:id,name,email')
            ->when($data['actor'] ?? null, fn ($query, $actor) => $query->where('actor_user_id', $actor))
            ->when($data['action'] ?? null, fn ($query, $action) => $query->where('action', 'like', "%{$action}%"))
            ->when($data['entity'] ?? null, fn ($query, $entity) => $query->where('auditable_type', 'like', "%{$entity}%"))
            ->when($data['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->when($data['to'] ?? null, fn ($query, $to) => $query->where('created_at', '<=', $to))
            ->latest('created_at')
            ->paginate(30);

        return response()->json(['data' => $logs]);
    }

    public function auditLog(AuditLog $auditLog)
    {
        return response()->json(['data' => $auditLog->load('actor:id,name,email')]);
    }

    public function landingPages()
    {
        return response()->json(['data' => LandingPage::with('versions')->latest()->paginate(20)]);
    }

    public function uploadLandingPage(Request $request, LandingPagePackageValidator $validator)
    {
        $data = $request->validate(['package' => ['required', 'file', 'max:51200']]);
        $path = $data['package']->store('landing-pages/uploads');
        $validation = $validator->validate(storage_path('app/'.$path));

        return response()->json(['data' => ['upload_path' => $path] + $validation], 201);
    }

    public function settings()
    {
        return response()->json(['data' => DB::table('settings')->get()->groupBy('group')]);
    }

    public function updateSettings(Request $request, string $section)
    {
        foreach ($request->all() as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['group' => $section, 'key' => $key],
                ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()]
            );
        }
        $this->audit->log('settings.updated', null, ['section' => $section, 'keys' => array_keys($request->all())], $request);

        return $this->settings();
    }

    private function couponData(Request $request, bool $updating = false): array
    {
        $rules = [
            'code' => [$updating ? 'sometimes' : 'required', 'string', 'max:80', 'unique:coupons,code'.($updating ? ','.$request->route('coupon')->id : '')],
            'type' => [$updating ? 'sometimes' : 'required', 'in:percent,fixed'],
            'amount_minor' => ['nullable', 'integer', 'min:0'],
            'percentage_bps' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'status' => ['nullable', 'in:active,paused,expired,archived'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'minimum_order_minor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'bundle_ids' => ['nullable', 'array'],
            'bundle_ids.*' => ['integer', 'exists:bundles,id'],
        ];

        $data = $request->validate($rules);
        $type = $data['type'] ?? $request->route('coupon')?->type;

        if ($type === 'percent') {
            abort_unless(! empty($data['percentage_bps']) || $updating, 422, 'Percentage discount is required.');
            $data['amount_minor'] = null;
        }

        if ($type === 'fixed') {
            abort_unless(! empty($data['amount_minor']) || $updating, 422, 'Fixed discount amount is required.');
            $data['percentage_bps'] = null;
        }

        $productIds = $data['product_ids'] ?? [];
        $bundleIds = $data['bundle_ids'] ?? [];
        unset($data['product_ids'], $data['bundle_ids']);
        $data['code'] = isset($data['code']) ? strtoupper($data['code']) : $request->route('coupon')?->code;
        $data['status'] ??= 'active';
        $data['currency'] ??= 'BDT';
        $data['minimum_order_minor'] ??= 0;

        return ['attributes' => $data, 'product_ids' => $productIds, 'bundle_ids' => $bundleIds];
    }
}
