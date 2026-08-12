<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundAttempt extends Model
{
    protected $fillable = [
        'uuid', 'order_id', 'payment_transaction_id', 'gateway', 'idempotency_key',
        'provider_payment_id', 'provider_refund_id', 'refund_type', 'amount_minor',
        'currency', 'status', 'requested_by', 'requested_at', 'succeeded_at',
        'failed_at', 'raw_response',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'succeeded_at' => 'datetime',
        'failed_at' => 'datetime',
        'raw_response' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
