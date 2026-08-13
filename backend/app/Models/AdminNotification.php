<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    protected $fillable = [
        'uuid', 'type', 'title', 'message', 'url', 'entity_type', 'entity_id', 'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];
}
