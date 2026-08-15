<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactInquiry extends Model
{
    protected $fillable = [
        'uuid', 'name', 'email', 'subject', 'message', 'status', 'read_at', 'replied_at', 'resolved_at', 'admin_notes', 'ip_hash',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'replied_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function replies()
    {
        return $this->hasMany(ContactInquiryReply::class);
    }
}
