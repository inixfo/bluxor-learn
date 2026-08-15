<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'category_id', 'name', 'name_bn', 'slug', 'short_description', 'description',
        'product_type', 'status', 'regular_price_minor', 'sale_price_minor', 'currency',
        'cover_image_path', 'featured_image_path', 'featured', 'published_at', 'metadata',
    ];

    protected $casts = [
        'featured' => 'boolean',
        'published_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProductFile::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function bundles(): BelongsToMany
    {
        return $this->belongsToMany(Bundle::class)->withPivot('sort_order');
    }

    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class, 'product_resource');
    }
}
