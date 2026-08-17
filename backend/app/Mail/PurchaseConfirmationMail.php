<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class PurchaseConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly string $accessUrl,
        public readonly array $communities = []
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Your purchase is ready - '.$this->order->order_number)
            ->view('emails.purchase-confirmation')
            ->text('emails.purchase-confirmation-text')
            ->with($this->templateData());
    }

    public function templateData(): array
    {
        $this->order->loadMissing('items', 'paymentTransactions');
        $timezone = (string) config('learn.admin_timezone', 'Asia/Dhaka');

        return [
            'order' => $this->order,
            'accessUrl' => $this->accessUrl,
            'communities' => $this->communities,
            'items' => $this->items(),
            'supportEmail' => (string) config('learn.support_email', 'support@bluxor.com'),
            'frontendUrl' => rtrim((string) env('FRONTEND_URL', config('app.url')), '/'),
            'total' => $this->money((int) $this->order->total_minor, (string) $this->order->currency),
            'subtotal' => $this->money((int) $this->order->subtotal_minor, (string) $this->order->currency),
            'discount' => $this->money((int) $this->order->discount_minor, (string) $this->order->currency),
            'orderPlacedAt' => $this->dateTime($this->order->created_at, $timezone),
            'paymentConfirmedAt' => $this->dateTime($this->order->paymentTransactions->whereNotNull('paid_at')->sortByDesc('paid_at')->first()?->paid_at, $timezone),
            'preheader' => $this->communities
                ? 'Your purchase is confirmed. Access your products and community.'
                : 'Your purchase is confirmed and your digital products are ready.',
            'year' => now()->year,
        ];
    }

    private function items(): array
    {
        return $this->order->items->map(function ($item) {
            $product = $item->product_id ? Product::find($item->product_id) : null;

            return [
                'name' => $item->product_name,
                'type' => $item->purchasable_type === 'bundle' ? 'Digital Bundle' : 'Digital Product',
                'quantity' => $item->quantity,
                'total' => $this->money((int) $item->total_minor, (string) ($item->currency ?: $this->order->currency)),
                'cover' => $this->safeHttpsImage($product?->cover_image_path),
            ];
        })->values()->all();
    }

    private function safeHttpsImage(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $url = str_starts_with($path, 'http://') || str_starts_with($path, 'https://') ? $path : url($path);

        return str_starts_with($url, 'https://') ? $url : null;
    }

    private function money(int $minor, string $currency): string
    {
        return strtoupper($currency).' '.number_format($minor / 100);
    }

    private function dateTime(mixed $value, string $timezone): ?string
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value)->timezone($timezone)->format('M j, Y \a\t g:i A');
    }
}
