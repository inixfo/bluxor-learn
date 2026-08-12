<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PurchaseConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly string $accessUrl
    ) {}

    public function build(): self
    {
        $items = $this->order->items->map(fn ($item) => '<li>'.e($item->product_name).'</li>')->implode('');
        $amount = $this->order->currency.' '.number_format($this->order->total_minor / 100);

        return $this
            ->subject('Your Learn by Bluxor purchase: '.$this->order->order_number)
            ->html('
                <h1>Thank you for your purchase</h1>
                <p>Order: <strong>'.e($this->order->order_number).'</strong></p>
                <p>Total: <strong>'.e($amount).'</strong></p>
                <p>Purchased items:</p>
                <ul>'.$items.'</ul>
                <p><a href="'.e($this->accessUrl).'">Access your purchase securely</a></p>
                <p>Need help? Reply to this email or contact support@bluxor.com.</p>
            ');
    }
}
