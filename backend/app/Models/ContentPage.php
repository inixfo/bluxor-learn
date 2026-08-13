<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentPage extends Model
{
    protected $fillable = [
        'uuid', 'title', 'slug', 'content', 'meta_title', 'meta_description', 'status',
    ];
}
