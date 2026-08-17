<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LandingPage;
use App\Models\LandingPageVersion;
use App\Services\LandingPageEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LandingPageController extends Controller
{
    private const LANDING_MIME_TYPES = [
        'css' => 'text/css; charset=utf-8',
        'html' => 'text/html; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
    ];

    public function __construct(private readonly LandingPageEngine $engine) {}

    public function context(string $slug)
    {
        $page = LandingPage::with('publishedVersion')->where('slug', $slug)->where('status', 'published')->firstOrFail();
        abort_unless($page->publishedVersion, 404);
        $this->assertRenderableVersion($page->publishedVersion);

        return response()->json(['data' => $this->engine->context($page, $page->publishedVersion)]);
    }

    public function track(Request $request)
    {
        $data = $request->validate([
            'event_uuid' => ['nullable', 'uuid'],
            'event_name' => ['required', 'in:landing_page_view,cta_click,checkout_started,payment_initiated,custom_event'],
            'landing_page_id' => ['nullable', 'integer', 'exists:landing_pages,id'],
            'landing_page_version_id' => ['nullable', 'integer', 'exists:landing_page_versions,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'bundle_id' => ['nullable', 'integer', 'exists:bundles,id'],
            'visitor_id' => ['nullable', 'string', 'max:120'],
            'session_id' => ['nullable', 'string', 'max:120'],
            'properties' => ['nullable', 'array'],
        ]);

        DB::table('analytics_events')->updateOrInsert(
            ['event_uuid' => $data['event_uuid'] ?? (string) Str::uuid()],
            [
                'user_id' => $request->user()?->id,
                'landing_page_id' => $data['landing_page_id'] ?? null,
                'landing_page_version_id' => $data['landing_page_version_id'] ?? null,
                'product_id' => $data['product_id'] ?? null,
                'bundle_id' => $data['bundle_id'] ?? null,
                'visitor_key_hash' => isset($data['visitor_id']) ? hash('sha256', $data['visitor_id']) : null,
                'session_key_hash' => isset($data['session_id']) ? hash('sha256', $data['session_id']) : null,
                'event_name' => $data['event_name'],
                'properties' => json_encode($this->safeProperties($data['properties'] ?? [])),
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return response()->json(['data' => ['ok' => true]]);
    }

    public function serve(string $slug)
    {
        $this->assertSafeSlug($slug);
        $page = LandingPage::with('publishedVersion')->where('slug', $slug)->where('status', 'published')->firstOrFail();
        abort_unless($page->publishedVersion, 404);
        $this->assertRenderableVersion($page->publishedVersion);

        return $this->htmlResponse($page, $page->publishedVersion);
    }

    public function asset(string $slug, string $path)
    {
        $this->assertSafeSlug($slug);
        $page = LandingPage::with('publishedVersion')->where('slug', $slug)->where('status', 'published')->firstOrFail();
        abort_unless($page->publishedVersion, 404);
        $this->assertRenderableVersion($page->publishedVersion);

        try {
            $file = $this->engine->assetFile($page->publishedVersion, $path);
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            return $this->assetNotFound();
        }

        return $this->assetResponse(
            $file,
            'public, max-age=3600'
        );
    }

    public function preview(Request $request, LandingPageVersion $version)
    {
        abort_unless($request->hasValidSignature() || ($request->user()?->roles()->where('name', 'admin')->exists()), 403);
        $this->assertRenderableVersion($version);

        return $this->htmlResponse($version->landingPage()->firstOrFail(), $version, true)->withHeaders([
            'Content-Security-Policy' => $this->csp(),
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function previewAsset(Request $request, LandingPageVersion $version, string $path)
    {
        abort_unless($request->hasValidSignature() || ($request->user()?->roles()->where('name', 'admin')->exists()), 403);
        $this->assertRenderableVersion($version);

        try {
            $file = $this->engine->assetFile($version, $path);
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            return $this->assetNotFound(['X-Robots-Tag' => 'noindex, nofollow']);
        }

        return $this->assetResponse(
            $file,
            'no-store',
            ['X-Robots-Tag' => 'noindex, nofollow']
        );
    }

    public function runtime()
    {
        return response()->file(public_path('landing-runtime/lbx-runtime.v2.js'), [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function safeProperties(array $properties): array
    {
        unset($properties['email'], $properties['phone'], $properties['token'], $properties['password']);

        return $properties;
    }

    private function csp(): string
    {
        return "default-src 'self'; script-src 'self' https://connect.facebook.net; style-src 'self' 'unsafe-inline'; img-src 'self' data: https://www.facebook.com; font-src 'self' data:; media-src 'self' https:; connect-src 'self' https://www.facebook.com https://connect.facebook.net; object-src 'none'; frame-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'";
    }

    private function htmlResponse(LandingPage $page, LandingPageVersion $version, bool $preview = false)
    {
        $html = file_get_contents($this->engine->entryFile($version));
        $context = $this->engine->context($page, $version, $preview);
        $injected = $this->injectRuntime($html ?: '', $page, $version, $context, $preview);

        return response($injected, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => $this->csp(),
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $preview ? 'no-store' : 'no-cache, private',
        ]);
    }

    private function injectRuntime(string $html, LandingPage $page, LandingPageVersion $version, array $context, bool $preview): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML($html, LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            $dom->loadHTML('<!doctype html><html><head></head><body></body></html>', LIBXML_HTML_NODEFDTD);
        }

        $this->removePackageBaseElements($dom);

        $head = $this->documentHead($dom);
        $baseHref = $preview ? "/landing-preview/{$version->id}/" : "/go/{$page->slug}/";
        $contextJson = json_encode($context, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

        $nodes = [
            $this->element($dom, 'base', ['href' => $baseHref]),
            $this->element($dom, 'meta', ['name' => 'lbx-page-slug', 'content' => $page->slug]),
            $this->element($dom, 'meta', ['name' => 'lbx-page-version', 'content' => (string) $version->version_number]),
            $this->element($dom, 'script', ['type' => 'application/json', 'id' => 'lbx-context'], $contextJson ?: '{}'),
            $this->element($dom, 'script', ['src' => '/landing-runtime/lbx-runtime.v2.js', 'defer' => 'defer']),
        ];

        $first = $head->firstChild;
        foreach ($nodes as $node) {
            $head->insertBefore($node, $first);
        }

        return $dom->saveHTML() ?: $html;
    }

    private function assetResponse(string $file, string $cacheControl, array $headers = [])
    {
        $response = response()->file($file, array_merge([
            'Content-Security-Policy' => $this->csp(),
            'Cache-Control' => $cacheControl,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Type' => $this->landingMimeType($file),
        ], $headers));

        $response->headers->set('Content-Type', $this->landingMimeType($file));

        return $response;
    }

    private function assetNotFound(array $headers = [])
    {
        return response('Not Found', 404, array_merge([
            'Content-Security-Policy' => $this->csp(),
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Type' => 'text/plain; charset=utf-8',
        ], $headers));
    }

    private function landingMimeType(string $file): string
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        abort_unless(isset(self::LANDING_MIME_TYPES[$extension]), 404);

        return self::LANDING_MIME_TYPES[$extension];
    }

    private function removePackageBaseElements(\DOMDocument $dom): void
    {
        $bases = [];
        foreach ($dom->getElementsByTagName('base') as $base) {
            $bases[] = $base;
        }

        foreach ($bases as $base) {
            $base->parentNode?->removeChild($base);
        }
    }

    private function documentHead(\DOMDocument $dom): \DOMElement
    {
        $head = $dom->getElementsByTagName('head')->item(0);
        if ($head instanceof \DOMElement) {
            return $head;
        }

        $head = $dom->createElement('head');
        $html = $dom->getElementsByTagName('html')->item(0);

        if ($html) {
            $html->insertBefore($head, $html->firstChild);

            return $head;
        }

        $dom->appendChild($head);

        return $head;
    }

    private function element(\DOMDocument $dom, string $name, array $attributes, ?string $text = null): \DOMElement
    {
        $element = $dom->createElement($name);
        foreach ($attributes as $key => $value) {
            $element->setAttribute($key, $value);
        }

        if ($text !== null) {
            $element->appendChild($dom->createTextNode($text));
        }

        return $element;
    }

    private function assertSafeSlug(string $slug): void
    {
        abort_unless((bool) preg_match('/\A[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*\z/', $slug), 404);
    }

    private function assertRenderableVersion(LandingPageVersion $version): void
    {
        $manifest = $version->manifest ?? [];

        abort_unless(
            (int) ($manifest['schemaVersion'] ?? 0) === 2 && (string) ($manifest['sdkVersion'] ?? '') === '2',
            404
        );
    }
}
