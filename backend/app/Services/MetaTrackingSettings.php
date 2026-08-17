<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MetaTrackingSettings
{
    public function publicPayload(?Request $request = null): array
    {
        $pixelId = $this->pixelId();

        return [
            'meta' => [
                'enabled' => $this->browserPixelEnabled($request),
                'pixel_id' => $pixelId,
                'require_marketing_consent' => $this->requiresMarketingConsent(),
            ],
        ];
    }

    public function adminPayload(): array
    {
        return [
            'pixel_enabled' => $this->settingBool('pixel_enabled', false),
            'pixel_effective_enabled' => $this->browserPixelEnabled(),
            'pixel_id' => $this->pixelId(),
            'pixel_id_configured' => $this->pixelId() !== '',
            'pixel_env_enabled' => (bool) config('services.meta.pixel_enabled'),
            'capi_enabled' => $this->settingBool('capi_enabled', false),
            'capi_effective_enabled' => $this->capiEnabled(),
            'capi_env_enabled' => (bool) config('services.meta.capi_enabled'),
            'capi_token_configured' => $this->capiToken() !== '',
            'graph_api_version' => $this->graphApiVersion(),
            'test_event_code_configured' => $this->testEventCode() !== '',
            'require_marketing_consent' => $this->requiresMarketingConsent(),
        ];
    }

    public function browserPixelEnabled(?Request $request = null): bool
    {
        if (! (bool) config('services.meta.pixel_enabled') || ! $this->settingBool('pixel_enabled', false) || $this->pixelId() === '') {
            return false;
        }

        if ($request && ! (bool) config('services.meta.allow_local_pixel') && in_array($request->getHost(), ['localhost', '127.0.0.1'], true)) {
            return false;
        }

        return true;
    }

    public function capiEnabled(): bool
    {
        return (bool) config('services.meta.capi_enabled')
            && $this->settingBool('capi_enabled', false)
            && $this->pixelId() !== ''
            && $this->capiToken() !== '';
    }

    public function capiConfigured(): bool
    {
        return $this->pixelId() !== '' && $this->capiToken() !== '' && $this->graphApiVersion() !== '';
    }

    public function pixelId(): string
    {
        return trim((string) ($this->setting('pixel_id') ?: config('services.meta.pixel_id')));
    }

    public function capiToken(): string
    {
        return trim((string) config('services.meta.capi_access_token'));
    }

    public function graphApiVersion(): string
    {
        $version = trim((string) ($this->setting('graph_api_version') ?: config('services.meta.graph_api_version', 'v25.0')));

        return preg_match('/^v\d+\.\d+$/', $version) ? $version : 'v25.0';
    }

    public function testEventCode(): string
    {
        return trim((string) config('services.meta.capi_test_event_code'));
    }

    public function requiresMarketingConsent(): bool
    {
        return (bool) config('services.meta.require_marketing_consent');
    }

    public function trackingAllowed(?array $trackingContext): bool
    {
        if (! $this->requiresMarketingConsent()) {
            return true;
        }

        return (bool) ($trackingContext['marketing_consent'] ?? false);
    }

    private function setting(string $key): mixed
    {
        $value = DB::table('settings')->where('group', 'tracking')->where('key', $key)->value('value');
        if ($value === null) {
            return null;
        }

        return json_decode((string) $value, true);
    }

    private function settingBool(string $key, bool $default): bool
    {
        $value = $this->setting($key);

        return $value === null ? $default : (bool) $value;
    }
}
