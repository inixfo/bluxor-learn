<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'uuid', 'order_id', 'gateway', 'provider_transaction_id', 'provider_reference',
        'validation_id', 'amount_minor', 'currency', 'status', 'normalized_state',
        'initiated_at', 'paid_at', 'failed_at', 'verified_at', 'raw_response',
    ];

    protected $casts = [
        'initiated_at' => 'datetime',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'verified_at' => 'datetime',
        'raw_response' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
