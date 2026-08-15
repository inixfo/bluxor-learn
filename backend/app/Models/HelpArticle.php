<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpArticle extends Model
{
    protected $fillable = [
        'help_category_id', 'title', 'slug', 'summary', 'content', 'sort_order', 'is_featured', 'status', 'views',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'views' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(HelpCategory::class, 'help_category_id');
    }
}
