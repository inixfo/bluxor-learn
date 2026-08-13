<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicMediaService
{
    public function storeImage(UploadedFile $file, string $directory): string
    {
        $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        $path = trim($directory, '/').'/'.now()->format('Y/m').'/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->putFileAs(dirname($path), $file, basename($path));

        return Storage::disk('public')->url($path);
    }

    public function deleteIfManaged(?string $url): void
    {
        if (! $url || ! str_contains($url, '/storage/')) {
            return;
        }

        if ($this->isReferenced($url)) {
            return;
        }

        $path = Str::after($url, '/storage/');
        if ($path !== '' && ! str_contains($path, '../')) {
            Storage::disk('public')->delete($path);
        }
    }

    private function isReferenced(string $url): bool
    {
        return DB::table('products')->where('cover_image_path', $url)->orWhere('featured_image_path', $url)->exists()
            || DB::table('categories')->where('image_path', $url)->exists()
            || DB::table('bundles')->where('cover_image_path', $url)->exists();
    }
}
