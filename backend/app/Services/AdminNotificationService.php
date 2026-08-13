<?php

namespace App\Services;

use App\Models\AdminNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AdminNotificationService
{
    public function create(string $type, string $title, ?string $message = null, ?string $url = null, ?Model $entity = null): AdminNotification
    {
        $identity = [
            'type' => $type,
            'entity_type' => $entity ? get_class($entity) : null,
            'entity_id' => $entity?->getKey(),
        ];

        return AdminNotification::firstOrCreate($identity, [
            'uuid' => (string) Str::uuid(),
            'title' => $title,
            'message' => $message,
            'url' => $url,
        ]);
    }
}
