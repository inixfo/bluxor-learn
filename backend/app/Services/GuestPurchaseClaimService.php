<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuestPurchaseClaimService
{
    public function __construct(private readonly GuestAccessService $guestAccess) {}

    public function claimForVerifiedUser(User $user): array
    {
        if (! $user->hasVerifiedEmail()) {
            return $this->emptyResult();
        }

        $email = $this->normalizeEmail($user->email);
        if ($email === '') {
            return $this->emptyResult();
        }

        $orderIds = $this->verifiedEmailOrderQuery($user, $email)->pluck('id');

        return $this->claimOrderIds($user, $orderIds);
    }

    public function previewForVerifiedUser(User $user): array
    {
        if (! $user->hasVerifiedEmail()) {
            return ['matching_paid_guest_orders' => 0, 'potential_entitlements' => 0];
        }

        $email = $this->normalizeEmail($user->email);
        if ($email === '') {
            return ['matching_paid_guest_orders' => 0, 'potential_entitlements' => 0];
        }

        $orderIds = $this->verifiedEmailOrderQuery($user, $email)->pluck('id');

        return [
            'matching_paid_guest_orders' => $orderIds->count(),
            'potential_entitlements' => DB::table('entitlements')
                ->whereIn('order_id', $orderIds)
                ->whereNull('user_id')
                ->where('status', 'active')
                ->count(),
        ];
    }

    public function claimWithGuestToken(User $user, string $orderNumber, string $guestAccessToken): array
    {
        $accountEmail = $this->normalizeEmail($user->email);
        $orderNumber = trim($orderNumber);

        return DB::transaction(function () use ($user, $orderNumber, $guestAccessToken, $accountEmail) {
            $order = Order::where('order_number', $orderNumber)->lockForUpdate()->first();

            if (! $order) {
                throw ValidationException::withMessages(['order_number' => ['Order was not found.']]);
            }

            if ($order->payment_status !== 'paid') {
                throw new AuthorizationException('Only paid purchases can be claimed.');
            }

            if (! $this->guestAccess->resolve($order, $guestAccessToken)) {
                throw new AuthorizationException('Guest access token is invalid or expired.');
            }

            if ($accountEmail === '' || $accountEmail !== $this->normalizeEmail($order->customer_email)) {
                throw ValidationException::withMessages(['email' => ['Use the same email address you used when purchasing.']]);
            }

            if ($order->user_id !== null && (int) $order->user_id !== (int) $user->id) {
                throw new AuthorizationException('This purchase is already attached to another account.');
            }

            return $this->claimLockedOrders($user, collect([$order]));
        });
    }

    private function claimOrderIds(User $user, Collection $orderIds): array
    {
        $orderIds = $orderIds->map(fn ($id) => (int) $id)->unique()->values();

        if ($orderIds->isEmpty()) {
            return $this->emptyResult();
        }

        return DB::transaction(function () use ($user, $orderIds) {
            $orders = Order::whereIn('id', $orderIds)
                ->where('payment_status', 'paid')
                ->where(function ($query) use ($user) {
                    $query->whereNull('user_id')->orWhere('user_id', $user->id);
                })
                ->lockForUpdate()
                ->get(['id', 'user_id']);

            return $this->claimLockedOrders($user, $orders);
        });
    }

    private function claimLockedOrders(User $user, Collection $orders): array
    {
        $orderIds = $orders->pluck('id')->map(fn ($id) => (int) $id)->unique()->values();

        if ($orderIds->isEmpty()) {
            return $this->emptyResult();
        }

        $unattachedOrderIds = $orders
            ->filter(fn (Order $order) => $order->user_id === null)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $ordersClaimed = $unattachedOrderIds->isEmpty()
            ? 0
            : Order::whereIn('id', $unattachedOrderIds)
                ->whereNull('user_id')
                ->update(['user_id' => $user->id, 'updated_at' => now()]);

        $entitlements = DB::table('entitlements')
            ->whereIn('order_id', $orderIds)
            ->whereNull('user_id')
            ->where('status', 'active');

        $productsClaimed = (clone $entitlements)->distinct()->count('product_id');

        $entitlementsClaimed = $entitlements->update(['user_id' => $user->id, 'updated_at' => now()]);

        return [
            'orders_claimed' => $ordersClaimed,
            'entitlements_claimed' => $entitlementsClaimed,
            'products_claimed' => $productsClaimed,
        ];
    }

    private function verifiedEmailOrderQuery(User $user, string $email)
    {
        return Order::query()
            ->where('payment_status', 'paid')
            ->whereRaw('lower(customer_email) = ?', [$email])
            ->where(function ($query) use ($user) {
                $query->whereNull('user_id')->orWhere('user_id', $user->id);
            });
    }

    private function normalizeEmail(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    private function emptyResult(): array
    {
        return ['orders_claimed' => 0, 'entitlements_claimed' => 0, 'products_claimed' => 0];
    }
}
