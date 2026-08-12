<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'type', 'amount_minor', 'percentage_bps', 'status', 'starts_at', 'expires_at',
        'usage_limit', 'per_customer_limit', 'minimum_order_minor', 'currency', 'metadata',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function bundles(): BelongsToMany
    {
        return $this->belongsToMany(Bundle::class, 'coupon_bundle');
    }
}
