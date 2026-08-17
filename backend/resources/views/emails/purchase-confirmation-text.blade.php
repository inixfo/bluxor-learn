Learn by Bluxor
Digital knowledge. Practical results.

Purchase confirmed

Thank you for your purchase, {{ $order->customer_name }}.

Your payment was successfully received and your digital products are ready.

Order: {{ $order->order_number }}
Order placed: {{ $orderPlacedAt }}
@if ($paymentConfirmedAt)
Payment confirmed: {{ $paymentConfirmedAt }}
@endif
Payment: Paid
Total: {{ $total }}

Your purchase:
@foreach ($items as $item)
- {{ $item['name'] }} x {{ $item['quantity'] }} - {{ $item['total'] }}
@endforeach

Access your purchase:
{{ $accessUrl }}

@if (count($communities) > 0)
Community access:
@foreach ($communities as $community)
- {{ $community['name'] }}
  {{ $community['url'] }}
@endforeach

@endif
Order details:
Subtotal: {{ $subtotal }}
Discount: {{ $discount }}
Total: {{ $total }}

Need help?
Reply to this email or contact {{ $supportEmail }}.

{{ $frontendUrl }}

This is a transactional email sent because you completed a purchase at Learn by Bluxor.

Copyright {{ $year }} Bluxor
