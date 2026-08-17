<?php

namespace App\Services;

use App\Models\Entitlement;
use App\Models\ProductFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DownloadDeliveryService
{
    public function signedCustomerUrl(ProductFile $file, Entitlement $entitlement): array
    {
        return [
            'download_url' => URL::temporarySignedRoute('downloads.customer', now()->addMinutes(10), [
                'file' => $file->id,
                'entitlement' => $entitlement->id,
                'nonce' => Str::random(16),
            ], absolute: false),
            'expires_at' => now()->addMinutes(10)->toISOString(),
        ];
    }

    public function signedGuestUrl(ProductFile $file, Entitlement $entitlement, string $guestToken): array
    {
        return [
            'download_url' => URL::temporarySignedRoute('downloads.guest', now()->addMinutes(10), [
                'file' => $file->id,
                'entitlement' => $entitlement->id,
                'token' => $guestToken,
                'nonce' => Str::random(16),
            ], absolute: false),
            'expires_at' => now()->addMinutes(10)->toISOString(),
        ];
    }

    public function record(Request $request, ProductFile $file, Entitlement $entitlement): void
    {
        DB::table('download_events')->insert([
            'entitlement_id' => $entitlement->id,
            'user_id' => $entitlement->user_id,
            'order_id' => $entitlement->order_id,
            'product_id' => $file->product_id,
            'product_file_id' => $file->id,
            'customer_email' => $entitlement->customer_email,
            'ip_hash' => $request->ip() ? hash('sha256', $request->ip()) : null,
            'user_agent_hash' => $request->userAgent() ? hash('sha256', $request->userAgent()) : null,
            'downloaded_at' => now(),
        ]);
    }

    public function ensureDownloadable(ProductFile $file, Entitlement $entitlement): void
    {
        if ($file->status !== 'active' || $entitlement->status !== 'active' || $file->product_id !== $entitlement->product_id) {
            throw new HttpException(403, 'This file is not available for this entitlement.');
        }

        if ($entitlement->expires_at && $entitlement->expires_at->isPast()) {
            throw new HttpException(403, 'This entitlement has expired.');
        }
    }
}
