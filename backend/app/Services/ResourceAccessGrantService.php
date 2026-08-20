<?php

namespace App\Services;

use App\Models\Resource;
use App\Models\ResourceAccessGrant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResourceAccessGrantService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function grant(User $customer, Resource $resource, User $admin, ?string $expiresAt, string $reason, ?Request $request = null): array
    {
        $grant = DB::transaction(function () use ($customer, $resource, $admin, $expiresAt, $reason, $request) {
            $grant = ResourceAccessGrant::updateOrCreate(
                ['user_id' => $customer->id, 'resource_id' => $resource->id],
                [
                    'status' => 'active',
                    'granted_by_user_id' => $admin->id,
                    'reason' => $reason,
                    'granted_at' => now(),
                    'expires_at' => $expiresAt,
                    'revoked_at' => null,
                    'revoked_by_user_id' => null,
                    'revocation_reason' => null,
                ]
            );

            $this->audit->log('access.resource_granted', $grant, [
                'customer_user_id' => $customer->id,
                'customer_email' => $customer->email,
                'resource_id' => $resource->id,
                'resource_title' => $resource->title,
                'reason' => $reason,
                'expires_at' => $expiresAt,
                'timestamp' => now()->toISOString(),
            ], $request);

            return $grant;
        });

        return ['status' => 'granted', 'grant' => $grant->fresh('resource')];
    }

    public function revoke(ResourceAccessGrant $grant, User $admin, string $reason, ?Request $request = null): ResourceAccessGrant
    {
        if ($grant->status !== 'revoked') {
            $grant->forceFill([
                'status' => 'revoked',
                'revoked_at' => now(),
                'revoked_by_user_id' => $admin->id,
                'revocation_reason' => $reason,
            ])->save();

            $this->audit->log('access.resource_revoked', $grant, [
                'customer_user_id' => $grant->user_id,
                'resource_id' => $grant->resource_id,
                'reason' => $reason,
                'timestamp' => now()->toISOString(),
            ], $request);
        }

        return $grant->fresh('resource');
    }
}
