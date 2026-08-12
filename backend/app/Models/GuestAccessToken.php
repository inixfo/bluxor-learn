<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestAccessToken extends Model
{
    protected $fillable = ['order_id', 'token_hash', 'email', 'expires_at', 'last_used_at', 'revoked_at'];

    protected $casts = ['expires_at' => 'datetime', 'last_used_at' => 'datetime', 'revoked_at' => 'datetime'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
