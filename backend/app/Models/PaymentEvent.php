<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentEvent extends Model
{
    protected $fillable = [
        'gateway', 'event_key', 'provider_transaction_id', 'order_id', 'processed_at', 'payload_hash', 'payload',
    ];

    protected $casts = ['processed_at' => 'datetime', 'payload' => 'array'];
}
