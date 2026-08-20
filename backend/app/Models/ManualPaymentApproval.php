<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualPaymentApproval extends Model
{
    protected $fillable = [
        'order_id', 'approved_by_user_id', 'amount_minor', 'currency', 'payment_method',
        'reference', 'reason', 'approved_at', 'metadata',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
