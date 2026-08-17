<?php

namespace App\Services;

use App\Models\Order;

class MetaEventIdService
{
    public function purchase(Order $order): string
    {
        return 'purchase:'.$order->order_number;
    }
}
