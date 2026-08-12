<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'purchasable_type', 'purchasable_id', 'product_id', 'bundle_id', 'product_name',
        'product_slug', 'quantity', 'unit_price_minor', 'discount_minor', 'total_minor', 'currency', 'snapshot',
    ];

    protected $casts = ['snapshot' => 'array'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
