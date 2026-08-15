<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResourceVersion extends Model
{
    protected $fillable = [
        'resource_id', 'version', 'storage_disk', 'storage_path', 'original_filename',
        'mime_type', 'file_size', 'created_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function resource()
    {
        return $this->belongsTo(Resource::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
