<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\ContactInquiry;
use App\Models\Coupon;
use App\Models\Entitlement;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\Resource;
use App\Models\ResourceAccessGrant;
use App\Models\User;
use App\Jobs\SendPurchaseConfirmationEmail;
use App\Services\AuditLogger;
use App\Services\LandingPagePackageValidator;
use App\Services\ManualPaymentApprovalService;
use App\Services\ManualProductAccessService;
use App\Services\ProductCommunityAccessService;
use App\Services\PublicMediaService;
use App\Services\ResourceAccessGrantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ProductCommunityAccessService $communities
    ) {}

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
            'community_enabled' => ['nullable', 'boolean'],
            'community_name' => ['nullable', 'required_if:community_enabled,1,true', 'string', 'max:255'],
            'community_url' => ['nullable', 'required_if:community_enabled,1,true', 'url', 'max:2048'],
        ]);

        unset($data['cover_image']);
        $data = $this->normalizeCommunityFields($data);
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
            'community_enabled' => ['nullable', 'boolean'],
            'community_name' => ['nullable', 'required_if:community_enabled,1,true', 'string', 'max:255'],
            'community_url' => ['nullable', 'required_if:community_enabled,1,true', 'url', 'max:2048'],
        ]);

        $oldCover = $product->cover_image_path;
        unset($data['cover_image']);
        $data = $this->normalizeCommunityFields($data);
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
        $orders = Order::with('items', 'entitlements.product', 'paymentTransactions', 'user.roles')->latest()->paginate(20);
        $orders->getCollection()->transform(fn (Order $order) => $this->adminOrderPayload($order));

        return response()->json(['data' => $orders]);
    }

    public function showOrder(Order $order)
    {
        return response()->json(['data' => $this->adminOrderPayload($order->load('items', 'entitlements.product', 'paymentTransactions', 'user.roles'), true)]);
    }

    public function cancelOrder(Request $request, Order $order)
    {
        abort_if($order->payment_status === 'paid', 422, 'Paid orders cannot be cancelled from admin.');
        abort_if($order->payment_status === 'refunded', 422, 'Refunded orders cannot be cancelled.');

        $order->forceFill([
            'order_status' => 'cancelled',
            'payment_status' => $order->payment_status === 'pending' ? 'cancelled' : $order->payment_status,
        ])->save();

        $this->audit->log('order.cancelled', $order, ['order_number' => $order->order_number], $request);

        return $this->showOrder($order);
    }

    public function resendOrderEmail(Request $request, Order $order)
    {
        abort_unless($order->payment_status === 'paid', 422, 'Purchase emails can only be resent for paid orders.');

        SendPurchaseConfirmationEmail::dispatch($order->id);
        $this->audit->log('order.email_resent', $order, ['order_number' => $order->order_number], $request);

        return response()->json(['data' => ['message' => 'Purchase email queued.']]);
    }

    public function updateOrderNotes(Request $request, Order $order)
    {
        $data = $request->validate(['admin_notes' => ['nullable', 'string', 'max:5000']]);
        $metadata = $order->metadata ?: [];
        $metadata['admin_notes'] = $data['admin_notes'] ?? null;
        $order->forceFill(['metadata' => $metadata])->save();

        $this->audit->log('order.notes_updated', $order, ['order_number' => $order->order_number], $request);

        return $this->showOrder($order);
    }

    public function approveOrderPayment(Request $request, Order $order, ManualPaymentApprovalService $manualPayments)
    {
        $data = $request->validate([
            'confirmation' => ['accepted'],
            'amount_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'payment_method' => ['required', Rule::in(['piprapay_manual', 'bkash', 'nagad', 'bank_transfer', 'cash', 'other'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $next = $manualPayments->approve($order, $request->user(), $data, $request);

        return response()->json([
            'data' => $this->adminOrderPayload($next->load('items', 'entitlements.product', 'paymentTransactions', 'manualPaymentApproval.approver', 'user.roles'), true),
        ]);
    }

    public function customers()
    {
        $users = User::with('roles')->get()->keyBy(fn ($user) => strtolower($user->email));
        $orders = Order::with('items')->get()->groupBy(fn ($order) => strtolower($order->customer_email));

        $rows = collect($users->keys())->merge($orders->keys())->unique()->map(function ($email) use ($users, $orders) {
            return $this->adminCustomerSummary($email, $users->get($email), $orders->get($email, collect()));
        })->sortByDesc('last_purchase_at')->values();

        return response()->json(['data' => ['data' => $rows, 'total' => $rows->count()]]);
    }

    public function showCustomer(string $customerKey)
    {
        [$user, $email] = $this->customerFromKey($customerKey);
        $orders = Order::with('items', 'entitlements.product', 'paymentTransactions', 'user.roles')
            ->whereRaw('lower(customer_email) = ?', [$email])
            ->latest()
            ->get();
        $entitlements = \App\Models\Entitlement::with('product')
            ->whereRaw('lower(customer_email) = ?', [$email])
            ->latest()
            ->get();

        return response()->json(['data' => [
            'summary' => $this->adminCustomerSummary($email, $user, $orders),
            'orders' => $orders->map(fn (Order $order) => $this->adminOrderPayload($order))->values(),
            'entitlements' => $entitlements->map(fn ($entitlement) => [
                'id' => $entitlement->id,
                'product_id' => $entitlement->product_id,
                'product_name' => $entitlement->product?->name,
                'status' => $entitlement->status,
                'grant_source' => $entitlement->grant_source ?? 'purchase',
                'access_label' => ($entitlement->grant_source ?? 'purchase') === 'admin_manual' ? 'Manual Product Access' : 'Purchased Access',
                'can_revoke' => ($entitlement->grant_source ?? 'purchase') === 'admin_manual' && $entitlement->order_id === null && $entitlement->status === 'active',
                'order_id' => $entitlement->order_id,
                'granted_at' => $entitlement->granted_at,
                'expires_at' => $entitlement->expires_at,
            ])->values(),
            'resource_grants' => $user ? $this->resourceGrantPayloads($user) : [],
        ]]);
    }

    public function customerAccess(User $user)
    {
        return response()->json(['data' => [
            'entitlements' => Entitlement::with('product')->where('user_id', $user->id)->latest()->get()->map(fn ($entitlement) => $this->accessEntitlementPayload($entitlement))->values(),
            'resource_grants' => $this->resourceGrantPayloads($user),
        ]]);
    }

    public function grantProductAccess(Request $request, User $user, ManualProductAccessService $manualAccess)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'reason' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        $product = Product::where('id', $data['product_id'])->whereIn('status', ['published', 'archived'])->firstOrFail();
        $result = $manualAccess->grant($user, $product, $request->user(), $data['expires_at'] ?? null, $data['reason'], $request);

        return response()->json(['data' => [
            'status' => $result['status'],
            'entitlement' => $this->accessEntitlementPayload($result['entitlement']->loadMissing('product')),
        ]], $result['status'] === 'granted' ? 201 : 200);
    }

    public function grantResourceAccess(Request $request, User $user, ResourceAccessGrantService $resourceGrants)
    {
        $data = $request->validate([
            'resource_id' => ['required', 'integer', 'exists:resources,id'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'reason' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        $resource = Resource::where('id', $data['resource_id'])->whereIn('status', ['published', 'archived'])->firstOrFail();
        $result = $resourceGrants->grant($user, $resource, $request->user(), $data['expires_at'] ?? null, $data['reason'], $request);

        return response()->json(['data' => [
            'status' => $result['status'],
            'grant' => $this->resourceGrantPayload($result['grant']->loadMissing('resource')),
        ]], 201);
    }

    public function revokeProductAccess(Request $request, Entitlement $entitlement, ManualProductAccessService $manualAccess)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:5000']]);
        $entitlement = $manualAccess->revoke($entitlement, $request->user(), $data['reason'], $request);

        return response()->json(['data' => $this->accessEntitlementPayload($entitlement->loadMissing('product'))]);
    }

    public function revokeResourceAccess(Request $request, ResourceAccessGrant $grant, ResourceAccessGrantService $resourceGrants)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:5000']]);
        $grant = $resourceGrants->revoke($grant, $request->user(), $data['reason'], $request);

        return response()->json(['data' => $this->resourceGrantPayload($grant->loadMissing('resource'))]);
    }

    public function suspendCustomer(Request $request, User $user)
    {
        $user->forceFill(['status' => 'suspended'])->save();
        $this->audit->log('customer.suspended', $user, ['email' => $user->email], $request);

        return response()->json(['data' => $this->adminCustomerDetailForUser($user)]);
    }

    public function reactivateCustomer(Request $request, User $user)
    {
        $user->forceFill(['status' => 'active'])->save();
        $this->audit->log('customer.reactivated', $user, ['email' => $user->email], $request);

        return response()->json(['data' => $this->adminCustomerDetailForUser($user)]);
    }

    private function adminOrderPayload(Order $order, bool $detail = false): array
    {
        $order->loadMissing('items', 'entitlements.product', 'paymentTransactions', 'manualPaymentApproval.approver', 'user.roles');
        $transaction = $order->paymentTransactions->sortByDesc('created_at')->first();
        $paidTransaction = $order->paymentTransactions
            ->filter(fn ($payment) => $payment->paid_at)
            ->sortByDesc('paid_at')
            ->first();
        $user = $order->user ?: User::with('roles')->whereRaw('lower(email) = ?', [strtolower($order->customer_email)])->first();
        $checkoutType = $order->checkout_type;
        $metadata = $order->metadata ?: [];

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'customer_phone' => $order->customer_phone,
            'customer_key' => $this->customerKey($user, strtolower($order->customer_email)),
            'user_id' => $user?->id,
            'checkout_type' => $checkoutType,
            'checkout_type_label' => match ($checkoutType) {
                'guest' => 'Guest Checkout',
                'account' => 'Account Checkout',
                default => 'Unknown',
            },
            'current_account_status' => $this->currentAccountStatus($user, $order),
            'current_account_status_label' => $this->currentAccountStatusLabel($user, $order),
            'subtotal_minor' => $order->subtotal_minor,
            'discount_minor' => $order->discount_minor,
            'total_minor' => $order->total_minor,
            'currency' => $order->currency,
            'coupon_id' => $order->coupon_id,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'payment_gateway' => $transaction?->gateway,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
            'payment_completed_at' => $paidTransaction?->paid_at,
            'manual_payment_approval' => $order->manualPaymentApproval ? [
                'id' => $order->manualPaymentApproval->id,
                'amount_minor' => $order->manualPaymentApproval->amount_minor,
                'currency' => $order->manualPaymentApproval->currency,
                'payment_method' => $order->manualPaymentApproval->payment_method,
                'reference' => $order->manualPaymentApproval->reference,
                'reason' => $order->manualPaymentApproval->reason,
                'approved_at' => $order->manualPaymentApproval->approved_at,
                'approved_by' => $order->manualPaymentApproval->approver ? [
                    'id' => $order->manualPaymentApproval->approver->id,
                    'name' => $order->manualPaymentApproval->approver->name,
                    'email' => $order->manualPaymentApproval->approver->email,
                ] : null,
            ] : null,
            'admin_notes' => $metadata['admin_notes'] ?? null,
            'attribution' => $this->adminOrderAttribution($metadata),
            'communities' => $order->payment_status === 'paid' ? $this->communities->forOrder($order) : [],
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'product_slug' => $item->product_slug,
                'quantity' => $item->quantity,
                'unit_price_minor' => $item->unit_price_minor,
                'discount_minor' => $item->discount_minor,
                'total_minor' => $item->total_minor,
                'currency' => $item->currency,
                'product_id' => $item->product_id,
                'bundle_id' => $item->bundle_id,
                'purchasable_type' => $item->purchasable_type,
            ])->values(),
            'payment_transactions' => $order->paymentTransactions->map(fn ($payment) => [
                'id' => $payment->id,
                'gateway' => $payment->gateway,
                'provider_transaction_id' => $payment->provider_transaction_id,
                'provider_reference' => $payment->provider_reference,
                'validation_id' => $payment->validation_id,
                'amount_minor' => $payment->amount_minor,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'normalized_state' => $payment->normalized_state,
                'paid_at' => $payment->paid_at,
                'failed_at' => $payment->failed_at,
                'verified_at' => $payment->verified_at,
            ])->values(),
            'entitlements' => $order->entitlements->map(fn ($entitlement) => [
                'id' => $entitlement->id,
                'product_id' => $entitlement->product_id,
                'product_name' => $entitlement->product?->name,
                'status' => $entitlement->status,
                'granted_at' => $entitlement->granted_at,
                'expires_at' => $entitlement->expires_at,
            ])->values(),
            'actions' => [
                'can_cancel' => ! in_array($order->payment_status, ['paid', 'refunded', 'cancelled'], true),
                'can_approve_payment' => ! in_array($order->payment_status, ['paid', 'refunded'], true),
                'can_refund' => $order->payment_status === 'paid',
                'can_resend_email' => $order->payment_status === 'paid',
                'can_mark_paid' => false,
            ],
        ];
    }

    private function accessEntitlementPayload(Entitlement $entitlement): array
    {
        return [
            'id' => $entitlement->id,
            'product_id' => $entitlement->product_id,
            'product_name' => $entitlement->product?->name,
            'status' => $entitlement->status,
            'grant_source' => $entitlement->grant_source ?? 'purchase',
            'access_label' => ($entitlement->grant_source ?? 'purchase') === 'admin_manual' ? 'Manual Product Access' : 'Purchased Access',
            'can_revoke' => ($entitlement->grant_source ?? 'purchase') === 'admin_manual' && $entitlement->order_id === null && $entitlement->status === 'active',
            'order_id' => $entitlement->order_id,
            'granted_at' => $entitlement->granted_at,
            'expires_at' => $entitlement->expires_at,
            'revoked_at' => $entitlement->revoked_at,
            'grant_note' => $entitlement->grant_note,
        ];
    }

    private function resourceGrantPayloads(User $user)
    {
        return $user->resourceAccessGrants()
            ->with('resource')
            ->latest('granted_at')
            ->get()
            ->map(fn (ResourceAccessGrant $grant) => $this->resourceGrantPayload($grant))
            ->values();
    }

    private function resourceGrantPayload(ResourceAccessGrant $grant): array
    {
        return [
            'id' => $grant->id,
            'resource_id' => $grant->resource_id,
            'resource_title' => $grant->resource?->title,
            'resource_slug' => $grant->resource?->slug,
            'resource_type' => $grant->resource?->resource_type,
            'status' => $grant->status,
            'access_label' => 'Manual Resource Access',
            'can_revoke' => $grant->status === 'active',
            'granted_at' => $grant->granted_at,
            'expires_at' => $grant->expires_at,
            'revoked_at' => $grant->revoked_at,
            'reason' => $grant->reason,
            'revocation_reason' => $grant->revocation_reason,
        ];
    }

    private function adminOrderAttribution(array $metadata): array
    {
        $attribution = is_array($metadata['order_attribution'] ?? null) ? $metadata['order_attribution'] : [];
        $lastTouch = is_array($attribution['last_touch'] ?? null) ? $attribution['last_touch'] : [];
        $firstTouch = is_array($attribution['first_touch'] ?? null) ? $attribution['first_touch'] : [];
        $displayTouch = $lastTouch ?: $firstTouch;

        return [
            'source' => $displayTouch['source'] ?? 'Unknown',
            'medium' => $displayTouch['medium'] ?? null,
            'campaign' => $displayTouch['campaign'] ?? null,
            'content' => $displayTouch['content'] ?? null,
            'term' => $displayTouch['term'] ?? null,
            'landing_url' => $displayTouch['landing_url'] ?? $displayTouch['current_url'] ?? null,
            'path' => $displayTouch['path'] ?? null,
            'referrer' => $displayTouch['referrer'] ?? null,
            'referrer_host' => $displayTouch['referrer_host'] ?? null,
            'visitor_id' => $attribution['visitor_id'] ?? null,
            'session_id' => $attribution['session_id'] ?? null,
            'landing_page_id' => $attribution['landing_page_id'] ?? $metadata['landing_page_id'] ?? null,
            'landing_page_version_id' => $attribution['landing_page_version_id'] ?? null,
            'offer_key' => $attribution['offer_key'] ?? $metadata['offer_key'] ?? null,
            'first_touch' => $firstTouch ?: null,
            'last_touch' => $lastTouch ?: null,
        ];
    }

    private function adminCustomerSummary(string $email, ?User $user, $customerOrders): array
    {
        $customerOrders = collect($customerOrders);
        $paidOrders = $customerOrders->where('payment_status', 'paid');
        $pendingOrders = $customerOrders->whereIn('payment_status', ['pending', 'cancelled', 'failed']);
        $refundedOrders = $customerOrders->where('payment_status', 'refunded');
        $firstOrder = $customerOrders->sortBy('created_at')->first();
        $lastOrder = $customerOrders->sortByDesc('created_at')->first();
        $productNames = $customerOrders->flatMap(fn ($order) => $order->items->pluck('product_name'))->unique()->values();
        $paidRevenue = (int) $paidOrders->sum('total_minor');
        $refundedAmount = (int) $refundedOrders->sum('total_minor');

        return [
            'id' => $user?->id ?: crc32($email),
            'customer_key' => $this->customerKey($user, $email),
            'name' => $user?->name ?: ($lastOrder?->customer_name ?: 'Guest Customer'),
            'email' => $email,
            'phone' => $user?->phone ?: $lastOrder?->customer_phone,
            'account_status' => $user ? ($user->status ?: 'active') : 'guest',
            'account_status_label' => $user ? (($user->status === 'suspended') ? 'Suspended' : 'Registered') : 'No Account',
            'has_account' => (bool) $user,
            'verified' => (bool) $user?->email_verified_at,
            'orders_count' => $customerOrders->count(),
            'paid_orders_count' => $paidOrders->count(),
            'unpaid_orders_count' => $pendingOrders->count(),
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
            'last_order_number' => $lastOrder?->order_number,
            'auth_provider' => $user?->socialAccounts()->latest()->value('provider') ?: 'password',
            'roles' => $user?->roles ?? [],
        ];
    }

    private function adminCustomerDetailForUser(User $user): array
    {
        $email = strtolower($user->email);
        $orders = Order::with('items')->whereRaw('lower(customer_email) = ?', [$email])->get();

        return $this->adminCustomerSummary($email, $user->load('roles'), $orders);
    }

    private function currentAccountStatus(?User $user, Order $order): string
    {
        if (! $user) {
            return 'no_account';
        }

        if (($user->status ?: 'active') === 'suspended') {
            return 'suspended';
        }

        if ($order->checkout_type === 'guest') {
            return 'claimed';
        }

        return 'registered';
    }

    private function currentAccountStatusLabel(?User $user, Order $order): string
    {
        return match ($this->currentAccountStatus($user, $order)) {
            'no_account' => 'No Account',
            'suspended' => 'Suspended',
            'claimed' => 'Claimed',
            default => 'Registered',
        };
    }

    private function customerKey(?User $user, string $email): string
    {
        if ($user) {
            return 'user-'.$user->id;
        }

        return 'email-'.rtrim(strtr(base64_encode(strtolower($email)), '+/', '-_'), '=');
    }

    private function customerFromKey(string $customerKey): array
    {
        if (Str::startsWith($customerKey, 'user-')) {
            $user = User::with('roles')->findOrFail((int) Str::after($customerKey, 'user-'));

            return [$user, strtolower($user->email)];
        }

        abort_unless(Str::startsWith($customerKey, 'email-'), 404);
        $encoded = (string) Str::after($customerKey, 'email-');
        $email = base64_decode(strtr($encoded, '-_', '+/').str_repeat('=', (4 - strlen($encoded) % 4) % 4), true);
        abort_unless(is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL), 404);

        return [User::with('roles')->whereRaw('lower(email) = ?', [strtolower($email)])->first(), strtolower($email)];
    }

    private function normalizeCommunityFields(array $data): array
    {
        if (array_key_exists('community_enabled', $data)) {
            $data['community_enabled'] = (bool) $data['community_enabled'];
        }

        if (array_key_exists('community_enabled', $data) && $data['community_enabled'] === false) {
            $data['community_name'] = null;
            $data['community_url'] = null;
        }

        return $data;
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
