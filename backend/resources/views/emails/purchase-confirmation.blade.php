<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Your purchase is ready</title>
</head>
<body style="margin:0; padding:0; background:#eef4fb; color:#132238; font-family:Arial, Helvetica, sans-serif;">
<div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent; line-height:1px;">
    {{ $preheader }}
</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef4fb; margin:0; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; max-width:640px; background:#ffffff; border-radius:18px; overflow:hidden; border:1px solid #d9e5f3;">
                <tr>
                    <td style="padding:28px 28px 18px 28px; background:#ffffff;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td>
                                    <div style="font-size:22px; font-weight:800; color:#0f2d52; letter-spacing:0;">Learn by Bluxor</div>
                                    <div style="margin-top:5px; font-size:13px; color:#5f7087;">Digital knowledge. Practical results.</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:0 28px 26px 28px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f9ff; border:1px solid #dce9f8; border-radius:16px;">
                            <tr>
                                <td style="padding:28px;">
                                    <div style="display:inline-block; padding:6px 10px; border-radius:8px; background:#dff5e7; color:#176b3a; font-size:13px; font-weight:700;">Purchase Confirmed</div>
                                    <h1 style="margin:18px 0 8px 0; font-size:28px; line-height:34px; color:#10233f; font-weight:800;">Thank you for your purchase, {{ $order->customer_name }}</h1>
                                    <p style="margin:0; font-size:15px; line-height:24px; color:#4f6178;">Your payment was successfully received and your digital products are ready.</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:0 28px 24px 28px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2eaf4; border-radius:14px;">
                            <tr>
                                <td style="padding:18px; border-bottom:1px solid #edf2f7;">
                                    <div style="font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:#7890aa; font-weight:700;">Order</div>
                                    <div style="margin-top:6px; font-size:16px; color:#10233f; font-weight:800;">{{ $order->order_number }}</div>
                                </td>
                                <td style="padding:18px; border-bottom:1px solid #edf2f7;">
                                    <div style="font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:#7890aa; font-weight:700;">Purchased</div>
                                    <div style="margin-top:6px; font-size:15px; color:#10233f; font-weight:700;">{{ $orderPlacedAt }}</div>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:18px;">
                                    <div style="font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:#7890aa; font-weight:700;">Payment</div>
                                    <div style="margin-top:6px; font-size:15px; color:#176b3a; font-weight:800;">Paid</div>
                                </td>
                                <td style="padding:18px;">
                                    <div style="font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:#7890aa; font-weight:700;">Total</div>
                                    <div style="margin-top:6px; font-size:18px; color:#10233f; font-weight:800;">{{ $total }}</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:0 28px 24px 28px;">
                        <h2 style="margin:0 0 14px 0; font-size:18px; color:#10233f; font-weight:800;">Your Purchase</h2>
                        @foreach ($items as $item)
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px; border:1px solid #e2eaf4; border-radius:14px;">
                                <tr>
                                    <td width="86" style="padding:14px; vertical-align:top;">
                                        @if ($item['cover'])
                                            <img src="{{ $item['cover'] }}" alt="{{ $item['name'] }}" width="64" height="64" style="display:block; width:64px; height:64px; object-fit:cover; border-radius:10px; border:1px solid #e2eaf4;">
                                        @else
                                            <div style="width:64px; height:64px; border-radius:10px; background:#e7f0ff; color:#1b5fd0; text-align:center; line-height:64px; font-weight:800;">LB</div>
                                        @endif
                                    </td>
                                    <td style="padding:14px 14px 14px 0; vertical-align:top;">
                                        <div style="font-size:16px; line-height:22px; color:#10233f; font-weight:800;">{{ $item['name'] }}</div>
                                        <div style="margin-top:4px; font-size:13px; color:#61758f;">{{ $item['type'] }} x {{ $item['quantity'] }}</div>
                                        <div style="margin-top:8px; font-size:13px; color:#10233f; font-weight:700;">{{ $item['total'] }}</div>
                                    </td>
                                </tr>
                            </table>
                        @endforeach

                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:16px;">
                            <tr>
                                <td style="border-radius:10px; background:#1b5fd0;">
                                    <a href="{{ $accessUrl }}" style="display:inline-block; padding:14px 22px; color:#ffffff; text-decoration:none; font-size:15px; font-weight:800;">Access Your Purchase</a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                @if (count($communities) > 0)
                    <tr>
                        <td style="padding:0 28px 24px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fbff; border:1px solid #dce9f8; border-radius:16px;">
                                <tr>
                                    <td style="padding:24px;">
                                        <h2 style="margin:0; font-size:18px; color:#10233f; font-weight:800;">Join Your Community</h2>
                                        <p style="margin:8px 0 18px 0; font-size:14px; line-height:22px; color:#4f6178;">Learning gets easier when you have people to learn with. Join other learners, ask questions, share workflows, get help and stay connected.</p>
                                        @foreach ($communities as $community)
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px; border-top:1px solid #e2eaf4;">
                                                <tr>
                                                    <td style="padding-top:14px;">
                                                        <div style="font-size:15px; color:#10233f; font-weight:800;">{{ $community['name'] }}</div>
                                                        <div style="margin-top:4px; font-size:12px; color:#61758f;">Community access is included with your purchase.</div>
                                                    </td>
                                                    <td align="right" style="padding-top:14px;">
                                                        <a href="{{ $community['url'] }}" style="display:inline-block; padding:10px 14px; border-radius:9px; border:1px solid #b9cbea; color:#1b5fd0; text-decoration:none; font-size:13px; font-weight:800;">Join Facebook Community</a>
                                                    </td>
                                                </tr>
                                            </table>
                                        @endforeach
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endif

                <tr>
                    <td style="padding:0 28px 24px 28px;">
                        <h2 style="margin:0 0 14px 0; font-size:18px; color:#10233f; font-weight:800;">Order Details</h2>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                            <tr><td style="padding:9px 0; color:#61758f; font-size:14px;">Order #</td><td align="right" style="padding:9px 0; color:#10233f; font-size:14px; font-weight:700;">{{ $order->order_number }}</td></tr>
                            <tr><td style="padding:9px 0; color:#61758f; font-size:14px;">Products</td><td align="right" style="padding:9px 0; color:#10233f; font-size:14px; font-weight:700;">{{ collect($items)->map(fn ($item) => $item['name'].' x '.$item['quantity'])->join(', ') }}</td></tr>
                            <tr><td style="padding:9px 0; color:#61758f; font-size:14px;">Subtotal</td><td align="right" style="padding:9px 0; color:#10233f; font-size:14px; font-weight:700;">{{ $subtotal }}</td></tr>
                            <tr><td style="padding:9px 0; color:#61758f; font-size:14px;">Discount</td><td align="right" style="padding:9px 0; color:#10233f; font-size:14px; font-weight:700;">{{ $discount }}</td></tr>
                            <tr><td style="padding:9px 0; color:#61758f; font-size:14px;">Payment confirmed</td><td align="right" style="padding:9px 0; color:#10233f; font-size:14px; font-weight:700;">{{ $paymentConfirmedAt ?: 'Paid' }}</td></tr>
                            <tr><td style="padding:12px 0; color:#10233f; font-size:15px; font-weight:800; border-top:1px solid #e2eaf4;">Total</td><td align="right" style="padding:12px 0; color:#10233f; font-size:15px; font-weight:800; border-top:1px solid #e2eaf4;">{{ $total }}</td></tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:0 28px 28px 28px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#10233f; border-radius:16px;">
                            <tr>
                                <td style="padding:22px;">
                                    <h2 style="margin:0; font-size:17px; color:#ffffff; font-weight:800;">Need help accessing your purchase?</h2>
                                    <p style="margin:8px 0 16px 0; font-size:14px; line-height:22px; color:#d7e4f3;">Reply to this email or contact {{ $supportEmail }}.</p>
                                    <a href="mailto:{{ $supportEmail }}" style="display:inline-block; padding:10px 14px; border-radius:9px; background:#ffffff; color:#10233f; text-decoration:none; font-size:13px; font-weight:800;">Contact Support</a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:24px 28px 30px 28px; background:#f7faff; border-top:1px solid #e2eaf4; text-align:center;">
                        <div style="font-size:14px; color:#10233f; font-weight:800;">Learn by Bluxor</div>
                        <div style="margin-top:4px; font-size:12px; color:#61758f;">Digital products by Bluxor</div>
                        <div style="margin-top:12px;"><a href="{{ $frontendUrl }}" style="color:#1b5fd0; font-size:12px; text-decoration:none;">learn.bluxor.com</a></div>
                        <p style="margin:14px 0 0 0; font-size:11px; line-height:18px; color:#7890aa;">This is a transactional email sent because you completed a purchase at Learn by Bluxor.</p>
                        <p style="margin:8px 0 0 0; font-size:11px; color:#7890aa;">&copy; {{ $year }} Bluxor</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
