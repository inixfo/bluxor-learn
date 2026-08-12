<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingPageOffer extends Model
{
    protected $fillable = [
        'landing_page_id', 'landing_page_version_id', 'offer_key', 'offer_type',
        'product_id', 'bundle_id', 'sort_order', 'is_primary',
    ];

    protected $casts = ['is_primary' => 'boolean'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class);
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }
}
