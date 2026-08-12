<?php

namespace App\Jobs;

use App\Mail\PurchaseConfirmationMail;
use App\Models\Order;
use App\Services\GuestAccessService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendPurchaseConfirmationEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public readonly int $orderId) {}

    public function handle(GuestAccessService $guestAccess): void
    {
        $order = Order::with('items')->findOrFail($this->orderId);

        $accessUrl = $order->user_id
            ? rtrim(env('FRONTEND_URL', 'http://127.0.0.1:5173'), '/').'/account/orders/'.$order->order_number
            : rtrim(env('FRONTEND_URL', 'http://127.0.0.1:5173'), '/').'/checkout/success?'.http_build_query([
                'order' => $order->order_number,
                'guest_access_token' => $guestAccess->issue($order),
            ]);

        Mail::to($order->customer_email)->send(new PurchaseConfirmationMail($order, $accessUrl));
    }
}
