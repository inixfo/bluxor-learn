<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaConversionEvent extends Model
{
    protected $fillable = [
        'event_name', 'event_id', 'order_id', 'status', 'attempts',
        'last_error_code', 'last_error_message', 'sent_at',
    ];

    protected $casts = ['sent_at' => 'datetime'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
