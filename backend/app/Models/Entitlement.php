<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Entitlement extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'order_id', 'order_item_id', 'product_id', 'customer_email',
        'status', 'granted_at', 'expires_at', 'revoked_at', 'revocation_reason', 'revocation_reference',
    ];

    protected $casts = ['granted_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
