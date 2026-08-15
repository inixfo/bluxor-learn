<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactInquiryReply extends Model
{
    protected $fillable = [
        'contact_inquiry_id', 'admin_user_id', 'sent_to', 'subject', 'message',
    ];

    public function inquiry()
    {
        return $this->belongsTo(ContactInquiry::class, 'contact_inquiry_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
