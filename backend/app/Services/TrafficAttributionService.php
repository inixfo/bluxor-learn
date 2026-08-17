<?php

namespace App\Services;

class TrafficAttributionService
{
    public function normalize(array $input): array
    {
        $referrer = $this->string($input['referrer'] ?? null, 2048);
        $referrerHost = $this->string($input['referrer_host'] ?? null, 255) ?: $this->host($referrer);
        $utmSource = $this->string($input['utm_source'] ?? $input['source'] ?? null, 255);
        $utmMedium = $this->string($input['utm_medium'] ?? $input['medium'] ?? null, 255);
        $source = $utmSource ? $this->sourceLabel($utmSource) : $this->sourceFromReferrer($referrerHost);
        $medium = $utmMedium ?: $this->mediumFrom($utmSource, $referrerHost);

        return array_filter([
            'source' => $source,
            'medium' => $medium,
            'campaign' => $this->string($input['utm_campaign'] ?? $input['campaign'] ?? null, 255),
            'content' => $this->string($input['utm_content'] ?? $input['content'] ?? null, 255),
            'term' => $this->string($input['utm_term'] ?? $input['term'] ?? null, 255),
            'referrer' => $referrer,
            'referrer_host' => $referrerHost,
            'landing_url' => $this->string($input['landing_url'] ?? $input['current_url'] ?? null, 2048),
            'path' => $this->string($input['path'] ?? null, 512),
            'landing_page_id' => $input['landing_page_id'] ?? null,
            'landing_page_version_id' => $input['landing_page_version_id'] ?? null,
            'fbclid' => $this->string($input['fbclid'] ?? null, 512),
            'gclid' => $this->string($input['gclid'] ?? null, 512),
            'msclkid' => $this->string($input['msclkid'] ?? null, 512),
            'ttclid' => $this->string($input['ttclid'] ?? null, 512),
            'occurred_at' => $input['occurred_at'] ?? now()->toISOString(),
            'raw' => $this->raw($input),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    public function shouldReplaceLastTouch(?array $current, array $candidate): bool
    {
        if (! $current) {
            return true;
        }

        return strtolower((string) ($candidate['source'] ?? 'Direct')) !== 'direct';
    }

    private function sourceLabel(string $source): string
    {
        return match (strtolower(trim($source))) {
            'fb', 'facebook', 'meta' => 'Facebook',
            'ig', 'instagram' => 'Instagram',
            'google', 'adwords' => 'Google',
            'bing', 'microsoft' => 'Microsoft',
            'tiktok', 'tt' => 'TikTok',
            'direct' => 'Direct',
            default => str($source)->replace(['_', '-'], ' ')->title()->toString(),
        };
    }

    private function sourceFromReferrer(?string $host): string
    {
        $host = strtolower((string) $host);
        if ($host === '') {
            return 'Direct';
        }

        if (str_contains($host, 'google.')) {
            return 'Google';
        }

        if (str_contains($host, 'facebook.') || str_contains($host, 'instagram.')) {
            return 'Social referral';
        }

        return str($host)->replace('www.', '')->before('.')->title()->toString();
    }

    private function mediumFrom(?string $utmSource, ?string $referrerHost): string
    {
        if (! $utmSource && ! $referrerHost) {
            return 'direct';
        }

        $host = strtolower((string) $referrerHost);
        if (! $utmSource && str_contains($host, 'google.')) {
            return 'organic';
        }

        if (! $utmSource && (str_contains($host, 'facebook.') || str_contains($host, 'instagram.'))) {
            return 'referral';
        }

        return 'referral';
    }

    private function host(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? strtolower($host) : null;
    }

    private function string(mixed $value, int $limit): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }

    private function raw(array $input): array
    {
        return array_filter([
            'utm_source' => $this->string($input['utm_source'] ?? null, 255),
            'utm_medium' => $this->string($input['utm_medium'] ?? null, 255),
            'utm_campaign' => $this->string($input['utm_campaign'] ?? null, 255),
            'utm_content' => $this->string($input['utm_content'] ?? null, 255),
            'utm_term' => $this->string($input['utm_term'] ?? null, 255),
        ], fn ($value) => $value !== null && $value !== '');
    }
}
