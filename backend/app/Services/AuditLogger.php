<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function log(string $action, ?Model $entity = null, array $metadata = [], ?Request $request = null): void
    {
        $request ??= request();

        AuditLog::create([
            'actor_user_id' => $request?->user()?->id,
            'action' => $action,
            'auditable_type' => $entity ? $entity::class : null,
            'auditable_id' => $entity?->getKey(),
            'metadata' => $this->redact($metadata),
            'ip_hash' => $request?->ip() ? hash('sha256', $request->ip()) : null,
            'user_agent_hash' => $request?->userAgent() ? hash('sha256', $request->userAgent()) : null,
            'created_at' => now(),
        ]);
    }

    private function redact(array $metadata): array
    {
        foreach ($metadata as $key => $value) {
            if (preg_match('/password|token|secret|store_pass/i', (string) $key)) {
                $metadata[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $metadata[$key] = $this->redact($value);
            }
        }

        return $metadata;
    }
}
