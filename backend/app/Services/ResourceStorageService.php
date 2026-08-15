<?php

namespace App\Services;

use App\Models\Resource;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResourceStorageService
{
    private const ALLOWED_EXTENSIONS = [
        'json', 'csv', 'xlsx', 'xls', 'zip', 'pdf', 'docx', 'txt', 'md',
        'xml', 'yaml', 'yml', 'js', 'ts', 'py', 'php', 'html', 'css',
        'png', 'jpg', 'jpeg', 'webp',
    ];

    public function store(Resource $resource, UploadedFile $file, string $version, ?User $admin): array
    {
        $this->validate($file);

        $extension = strtolower($file->getClientOriginalExtension());
        $safeName = Str::uuid().($extension ? '.'.$extension : '');
        $path = $file->storeAs('resources/'.$resource->slug, $safeName, 'private');

        $data = [
            'version' => $version,
            'storage_disk' => 'private',
            'storage_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
            'file_size' => $file->getSize(),
        ];

        $resource->versions()->create($data + ['created_by' => $admin?->id]);

        return $data;
    }

    public function deleteCurrentFile(Resource $resource): void
    {
        if ($resource->storage_path) {
            Storage::disk($resource->storage_disk ?: 'private')->delete($resource->storage_path);
        }
    }

    public function validate(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages(['file' => 'This resource file type is not allowed.']);
        }

        if ($file->getSize() > (int) config('learn.resource_library.max_upload_bytes', 100 * 1024 * 1024)) {
            throw ValidationException::withMessages(['file' => 'Resources may not be larger than 100 MB.']);
        }
    }
}
