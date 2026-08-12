<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bundle extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'name', 'name_bn', 'slug', 'description', 'status', 'cover_image_path',
        'regular_value_minor', 'bundle_price_minor', 'sale_price_minor', 'currency', 'published_at',
    ];

    protected $casts = ['published_at' => 'datetime'];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('sort_order');
    }
}
