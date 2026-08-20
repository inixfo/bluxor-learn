<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Resource;
use App\Models\User;
use App\Services\GuestAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicResourceController extends Controller
{
    public function show(Request $request, string $slug, GuestAccessService $guestAccess)
    {
        $resource = Resource::with('products:id,name,slug,status')->where('slug', $slug)->firstOrFail();
        abort_if($resource->status === 'draft', 404);

        $authorized = $this->isAuthorized($request, $resource, $guestAccess);

        return response()->json(['data' => [
            'title' => $resource->title,
            'slug' => $resource->slug,
            'description' => $resource->description,
            'resource_type' => $resource->resource_type,
            'source_type' => $resource->source_type,
            'version' => $resource->version,
            'access_type' => $resource->access_type,
            'status' => $resource->status,
            'download_count' => $resource->download_count,
            'original_filename' => $resource->original_filename,
            'file_size' => $resource->file_size,
            'mime_type' => $resource->mime_type,
            'updated_at' => $resource->updated_at,
            'canonical_url' => '/r/'.$resource->slug,
            'download_url' => $authorized && $resource->status === 'published' ? $this->downloadUrl($request, $resource) : null,
            'authorized' => $authorized,
            'authorization_message' => $this->authorizationMessage($resource, $authorized),
            'products' => $resource->products->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'status' => $product->status,
            ])->values(),
        ]]);
    }

    public function download(Request $request, string $slug, GuestAccessService $guestAccess)
    {
        $resource = Resource::with('products:id')->where('slug', $slug)->firstOrFail();
        abort_unless($resource->status === 'published', 404);
        abort_unless($this->isAuthorized($request, $resource, $guestAccess), 403);

        $resource->increment('download_count');

        if ($resource->source_type === 'external_url') {
            abort_unless($resource->external_url, 404);

            return redirect()->away($resource->external_url);
        }

        abort_unless($resource->storage_path, 404);

        $disk = $resource->storage_disk ?: 'private';
        abort_unless(Storage::disk($disk)->exists($resource->storage_path), 404);

        $filename = $resource->original_filename ?: Str::slug($resource->title).'.'.pathinfo($resource->storage_path, PATHINFO_EXTENSION);

        return Storage::disk($disk)->download($resource->storage_path, $filename, [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Type' => $this->mimeTypeForPath($filename),
        ]);
    }

    private function isAuthorized(Request $request, Resource $resource, GuestAccessService $guestAccess): bool
    {
        if ($resource->status !== 'published') {
            return false;
        }

        if ($resource->access_type === 'public') {
            return true;
        }

        $productIds = $resource->products()->withTrashed()->pluck('products.id')->all();
        if ($productIds === []) {
            return $this->hasDirectResourceGrant($request, $resource);
        }

        $user = $request->user();
        if ($this->hasDirectResourceGrant($request, $resource)) {
            return true;
        }

        if ($user instanceof User && $user->entitlements()
            ->whereIn('product_id', $productIds)
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists()) {
            return true;
        }

        $orderNumber = $request->query('order_number');
        if (! is_string($orderNumber) || $orderNumber === '') {
            return false;
        }

        $order = Order::with('entitlements')
            ->where('order_number', $orderNumber)
            ->where('payment_status', 'paid')
            ->first();

        $guestToken = $request->query('guest_access_token');
        if (! $order || ! $guestAccess->resolve($order, is_string($guestToken) ? $guestToken : null)) {
            return false;
        }

        return $order->entitlements
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->whereIn('product_id', $productIds)
            ->isNotEmpty();
    }

    private function hasDirectResourceGrant(Request $request, Resource $resource): bool
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return false;
        }

        return $user->resourceAccessGrants()
            ->where('resource_id', $resource->id)
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    private function downloadUrl(Request $request, Resource $resource): string
    {
        $params = collect(['order_number', 'guest_access_token'])
            ->mapWithKeys(fn ($key) => [$key => $request->query($key)])
            ->filter(fn ($value) => is_string($value) && $value !== '');

        $query = $params->isNotEmpty() ? '?'.$params->map(fn ($value, $key) => $key.'='.rawurlencode($value))->implode('&') : '';

        return '/api/v1/resources/'.$resource->slug.'/download'.$query;
    }

    private function authorizationMessage(Resource $resource, bool $authorized): string
    {
        if ($resource->status === 'archived') {
            return 'This resource has been archived and is no longer available for download.';
        }

        if ($authorized) {
            return $resource->access_type === 'public'
                ? 'This resource is available to download.'
                : 'Your purchase access is verified.';
        }

        return 'Purchase one of the connected products to unlock this resource.';
    }

    private function mimeTypeForPath(string $filename): string
    {
        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'css' => 'text/css; charset=utf-8',
            'html' => 'text/html; charset=utf-8',
            'json' => 'application/json',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'csv' => 'text/csv; charset=utf-8',
            'txt', 'md', 'xml', 'yaml', 'yml' => 'text/plain; charset=utf-8',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };
    }
}
