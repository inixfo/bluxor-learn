<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\GuestAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class N8nAutomationLabResourceController extends Controller
{
    private const SLUG = 'n8n-automation-lab';
    private const PRODUCT_SLUG = 'n8n-automation-lab-bangla';

    public function manifest(Request $request, GuestAccessService $guestAccess): JsonResponse
    {
        $manifest = $this->loadManifest();
        $product = $this->resourceProduct();
        $authorized = $this->isAuthorized($request, $guestAccess, $product);

        return response()->json(['data' => $this->publicManifest($request, $manifest, $product, $authorized)]);
    }

    public function downloadMasterPack(Request $request, GuestAccessService $guestAccess): BinaryFileResponse
    {
        $manifest = $this->loadManifest();
        $pack = Arr::get($manifest, 'master_pack');

        abort_unless(is_array($pack), 404);
        abort_unless($this->isAuthorized($request, $guestAccess, $this->resourceProduct()), 403);

        return $this->downloadFromManifestEntry('files', $pack);
    }

    public function downloadProjectResource(Request $request, string $projectSlug, string $fileName, GuestAccessService $guestAccess): BinaryFileResponse
    {
        abort_unless(preg_match('/^project-\d{2}$/', $projectSlug) === 1, 404);
        abort_unless($this->isSafeFilename($fileName), 404);

        $manifest = $this->loadManifest();
        $project = collect(Arr::get($manifest, 'projects', []))
            ->first(fn ($item) => is_array($item) && Arr::get($item, 'slug') === $projectSlug);

        abort_unless(is_array($project), 404);

        $resource = collect(Arr::get($project, 'resources', []))
            ->first(fn ($item) => is_array($item) && Arr::get($item, 'public_file') === $fileName);

        abort_unless(is_array($resource), 404);
        abort_unless($this->isAuthorized($request, $guestAccess, $this->resourceProduct()), 403);

        return $this->downloadFromManifestEntry('files/'.$projectSlug, $resource);
    }

    private function downloadFromManifestEntry(string $relativeFolder, array $entry): BinaryFileResponse
    {
        $fileName = Arr::get($entry, 'public_file');
        abort_unless(is_string($fileName) && $this->isSafeFilename($fileName), 404);

        $extension = strtolower('.'.pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = Arr::get($this->loadManifest(), 'allowed_extensions', []);
        abort_unless(in_array($extension, $allowedExtensions, true), 404);

        $path = $this->libraryPath($relativeFolder.'/'.$fileName);
        abort_unless(File::exists($path) && File::isFile($path), 404);
        abort_unless((int) File::size($path) > 0, 404);

        return response()->download($path, $fileName, [
            'Content-Type' => Arr::get($entry, 'mime_type', 'application/octet-stream'),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function loadManifest(): array
    {
        $path = $this->libraryPath('manifest.json');
        abort_unless(File::exists($path), 404);

        $manifest = json_decode(File::get($path), true);
        abort_unless(is_array($manifest) && Arr::get($manifest, 'slug') === self::SLUG, 404);

        return $manifest;
    }

    private function publicManifest(Request $request, array $manifest, ?Product $product, bool $authorized): array
    {
        $query = $this->guestQueryString($request);
        $masterPack = Arr::get($manifest, 'master_pack', []);

        return [
            'title' => Arr::get($manifest, 'title'),
            'slug' => Arr::get($manifest, 'slug'),
            'generated_at' => Arr::get($manifest, 'generated_at'),
            'public_base_url' => Arr::get($manifest, 'public_base_url'),
            'index_url' => Arr::get($manifest, 'index_url'),
            'authorized' => $authorized,
            'authorization_message' => $this->authorizationMessage($product, $authorized),
            'product' => [
                'exists' => $product !== null,
                'slug' => self::PRODUCT_SLUG,
                'name' => $product?->name ?: 'n8n Automation Lab বাংলা',
                'status' => $product?->status,
                'product_url' => '/p/'.self::PRODUCT_SLUG,
            ],
            'master_pack' => $this->publicResourcePayload($masterPack, $authorized, '/resources/'.self::SLUG.'/download/master-pack'.$query, true),
            'projects' => collect(Arr::get($manifest, 'projects', []))
                ->filter(fn ($project) => is_array($project))
                ->map(fn ($project) => $this->publicProjectPayload($project, $authorized, $query))
                ->values()
                ->all(),
        ];
    }

    private function publicProjectPayload(array $project, bool $authorized, string $query): array
    {
        $resources = collect(Arr::get($project, 'resources', []))->filter(fn ($resource) => is_array($resource));
        $projectSlug = (string) Arr::get($project, 'slug');

        return [
            'project' => Arr::get($project, 'project'),
            'slug' => $projectSlug,
            'title' => Arr::get($project, 'title'),
            'page_url' => Arr::get($project, 'page_url'),
            'resource_count' => Arr::get($project, 'resource_count', $resources->count()),
            'resource_types' => $resources
                ->pluck('type')
                ->filter(fn ($type) => is_string($type) && $type !== '')
                ->unique()
                ->values()
                ->all(),
            'resources' => $authorized
                ? $resources->map(fn ($resource) => $this->publicResourcePayload(
                    $resource,
                    true,
                    '/resources/'.self::SLUG.'/download/'.$projectSlug.'/'.rawurlencode((string) Arr::get($resource, 'public_file')).$query
                ))->values()->all()
                : [],
        ];
    }

    private function publicResourcePayload(array $resource, bool $authorized, string $downloadUrl, bool $isMasterPack = false): array
    {
        $payload = [
            'name' => Arr::get($resource, 'name'),
            'type' => Arr::get($resource, 'type'),
            'original_file' => Arr::get($resource, 'original_file'),
            'bytes' => Arr::get($resource, 'bytes'),
            'mime_type' => Arr::get($resource, 'mime_type'),
        ];

        if ($isMasterPack) {
            $payload['title'] = Arr::get($resource, 'title');
        }

        if ($authorized) {
            $payload['download_url'] = $downloadUrl;
            $payload['public_file'] = Arr::get($resource, 'public_file');
        }

        return $payload;
    }

    private function resourceProduct(): ?Product
    {
        return Product::withTrashed()->where('slug', self::PRODUCT_SLUG)->first();
    }

    private function isAuthorized(Request $request, GuestAccessService $guestAccess, ?Product $product): bool
    {
        if (! $product) {
            return false;
        }

        $user = $request->user();
        if ($user instanceof User && $user->entitlements()
            ->where('product_id', $product->id)
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

        return $order->entitlements->contains(function ($entitlement) use ($product) {
            return (int) $entitlement->product_id === (int) $product->id
                && $entitlement->status === 'active'
                && $entitlement->revoked_at === null
                && ($entitlement->expires_at === null || $entitlement->expires_at->isFuture());
        });
    }

    private function guestQueryString(Request $request): string
    {
        $params = collect(['order_number', 'guest_access_token'])
            ->mapWithKeys(fn ($key) => [$key => $request->query($key)])
            ->filter(fn ($value) => is_string($value) && $value !== '');

        return $params->isNotEmpty()
            ? '?'.$params->map(fn ($value, $key) => $key.'='.rawurlencode($value))->implode('&')
            : '';
    }

    private function authorizationMessage(?Product $product, bool $authorized): string
    {
        if ($authorized) {
            return 'Your purchase access is verified.';
        }

        if (! $product) {
            return 'Create the n8n Automation Lab product in the admin panel to enable paid resource access.';
        }

        return 'Purchase n8n Automation Lab বাংলা to unlock the resource downloads.';
    }

    private function isSafeFilename(string $fileName): bool
    {
        $decoded = rawurldecode($fileName);

        return $fileName === basename($fileName)
            && $decoded === basename($decoded)
            && ! str_contains($decoded, '..')
            && ! str_contains($decoded, '/')
            && ! str_contains($decoded, '\\');
    }

    private function libraryPath(string $path = ''): string
    {
        return base_path('resources/public-resource-library/'.self::SLUG.($path !== '' ? '/'.$path : ''));
    }
}
