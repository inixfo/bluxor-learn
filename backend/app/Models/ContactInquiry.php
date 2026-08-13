<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactInquiry extends Model
{
    protected $fillable = [
        'uuid', 'name', 'email', 'subject', 'message', 'status', 'ip_hash',
    ];
}
