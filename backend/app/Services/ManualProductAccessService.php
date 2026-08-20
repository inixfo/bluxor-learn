<?php

namespace App\Services;

use App\Models\Entitlement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManualProductAccessService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function grant(User $customer, Product $product, User $admin, ?string $expiresAt, string $reason, ?Request $request = null): array
    {
        $active = $this->activeEntitlementQuery($customer, $product)->first();
        if ($active) {
            return ['status' => 'already_has_access', 'entitlement' => $active];
        }

        $entitlement = DB::transaction(function () use ($customer, $product, $admin, $expiresAt, $reason, $request) {
            $entitlement = Entitlement::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $customer->id,
                'order_id' => null,
                'order_item_id' => null,
                'product_id' => $product->id,
                'customer_email' => strtolower($customer->email),
                'grant_source' => 'admin_manual',
                'granted_by_user_id' => $admin->id,
                'grant_note' => $reason,
                'status' => 'active',
                'granted_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            $this->audit->log('access.product_granted', $entitlement, [
                'customer_user_id' => $customer->id,
                'customer_email' => $customer->email,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'reason' => $reason,
                'expires_at' => $expiresAt,
                'timestamp' => now()->toISOString(),
            ], $request);

            return $entitlement;
        });

        return ['status' => 'granted', 'entitlement' => $entitlement->fresh('product')];
    }

    public function revoke(Entitlement $entitlement, User $admin, string $reason, ?Request $request = null): Entitlement
    {
        if ($entitlement->grant_source !== 'admin_manual' || $entitlement->order_id !== null) {
            throw ValidationException::withMessages(['entitlement' => ['Only manual product grants can be revoked here.']]);
        }

        if ($entitlement->status !== 'revoked') {
            $entitlement->forceFill([
                'status' => 'revoked',
                'revoked_at' => now(),
                'revoked_by_user_id' => $admin->id,
                'revocation_reason' => $reason,
            ])->save();

            $this->audit->log('access.product_revoked', $entitlement, [
                'customer_user_id' => $entitlement->user_id,
                'product_id' => $entitlement->product_id,
                'reason' => $reason,
                'timestamp' => now()->toISOString(),
            ], $request);
        }

        return $entitlement->fresh('product');
    }

    private function activeEntitlementQuery(User $customer, Product $product)
    {
        return $customer->entitlements()
            ->where('product_id', $product->id)
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
