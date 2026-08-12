<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_user_id', 'action', 'auditable_type', 'auditable_id', 'metadata',
        'ip_hash', 'user_agent_hash', 'created_at',
    ];

    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
