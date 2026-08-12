<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingPageVersion extends Model
{
    protected $fillable = [
        'uuid', 'landing_page_id', 'version_number', 'package_path', 'original_package_path',
        'public_path', 'package_size_bytes', 'manifest', 'entry_path', 'checksum', 'sdk_version',
        'status', 'validation_report', 'created_by', 'published_at',
    ];

    protected $casts = ['manifest' => 'array', 'validation_report' => 'array', 'published_at' => 'datetime'];

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }
}
