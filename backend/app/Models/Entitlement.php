<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Entitlement extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'order_id', 'order_item_id', 'product_id', 'customer_email',
        'grant_source', 'granted_by_user_id', 'grant_note', 'status', 'granted_at',
        'expires_at', 'revoked_at', 'revoked_by_user_id', 'revocation_reason', 'revocation_reference',
    ];

    protected $casts = ['granted_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }
}
