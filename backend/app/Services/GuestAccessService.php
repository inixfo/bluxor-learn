<?php

namespace App\Services;

use App\Models\GuestAccessToken;
use App\Models\Order;
use Illuminate\Support\Str;

class GuestAccessService
{
    public function issue(Order $order): string
    {
        $token = Str::random(64);

        GuestAccessToken::create([
            'order_id' => $order->id,
            'token_hash' => hash('sha256', $token),
            'email' => strtolower($order->customer_email),
            'expires_at' => now()->addDays((int) config('learn.guest_access_days', 30)),
        ]);

        return $token;
    }

    public function resolve(Order $order, ?string $token): ?GuestAccessToken
    {
        if (! $token) {
            return null;
        }

        return GuestAccessToken::where('order_id', $order->id)
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
    }
}
