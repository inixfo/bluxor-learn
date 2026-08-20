<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Resource extends Model
{
    protected $fillable = [
        'title', 'slug', 'description', 'resource_type', 'source_type', 'external_url',
        'original_filename', 'storage_disk', 'storage_path', 'mime_type', 'file_size',
        'version', 'access_type', 'status', 'download_count', 'created_by',
    ];

    protected $casts = [
        'download_count' => 'integer',
        'file_size' => 'integer',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_resource');
    }

    public function versions()
    {
        return $this->hasMany(ResourceVersion::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function accessGrants()
    {
        return $this->hasMany(ResourceAccessGrant::class);
    }
}
