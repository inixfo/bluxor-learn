<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaqCategory extends Model
{
    protected $fillable = ['name', 'slug', 'sort_order', 'status'];

    public function items()
    {
        return $this->hasMany(FaqItem::class);
    }
}
