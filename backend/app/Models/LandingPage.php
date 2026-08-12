<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LandingPage extends Model
{
    use SoftDeletes;

    protected $fillable = ['uuid', 'name', 'slug', 'status', 'primary_product_id', 'published_version_id', 'created_by'];

    public function versions(): HasMany
    {
        return $this->hasMany(LandingPageVersion::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(LandingPageOffer::class);
    }

    public function primaryProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'primary_product_id');
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(LandingPageVersion::class, 'published_version_id');
    }
}
