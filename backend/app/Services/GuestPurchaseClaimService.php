<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class GuestPurchaseClaimService
{
    public function claimForVerifiedUser(User $user): array
    {
        if (! $user->hasVerifiedEmail()) {
            return ['orders_claimed' => 0, 'entitlements_claimed' => 0];
        }

        $email = strtolower($user->email);

        return DB::transaction(function () use ($user, $email) {
            $orders = DB::table('orders')
                ->whereNull('user_id')
                ->where('payment_status', 'paid')
                ->whereRaw('lower(customer_email) = ?', [$email])
                ->update(['user_id' => $user->id, 'updated_at' => now()]);

            $entitlements = DB::table('entitlements')
                ->whereNull('user_id')
                ->where('status', 'active')
                ->whereRaw('lower(customer_email) = ?', [$email])
                ->update(['user_id' => $user->id, 'updated_at' => now()]);

            return ['orders_claimed' => $orders, 'entitlements_claimed' => $entitlements];
        });
    }
}
