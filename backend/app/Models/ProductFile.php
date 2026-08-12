<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductFile extends Model
{
    protected $fillable = [
        'uuid', 'product_id', 'name', 'file_type', 'file_size_bytes', 'storage_disk',
        'storage_path', 'version', 'download_limit', 'download_expiration_days', 'status', 'sort_order',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
