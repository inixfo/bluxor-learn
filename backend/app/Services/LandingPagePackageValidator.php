<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class LandingPagePackageValidator
{
    public function validate(string $path): array
    {
        $report = $this->inspect($path);

        if ($report['errors']) {
            throw ValidationException::withMessages(['package' => $report['errors']]);
        }

        return $report;
    }

    public function inspect(string $path): array
    {
        $checks = [];
        $errors = [];
        $warnings = [];
        $manifest = null;
        $entries = [];
        $normalizedEntries = [];
        $expandedBytes = 0;
        $compressedBytes = 0;

        $maxZipBytes = (int) config('learn.landing_packages.max_zip_bytes');
        if (! is_file($path) || filesize($path) > $maxZipBytes) {
            $errors[] = 'Package exceeds the configured ZIP size limit.';
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return $this->report(null, [], ['Package is not a valid ZIP archive.'], ['ZIP archive readable' => false]);
        }

        $checks['ZIP archive readable'] = true;
        $maxFiles = (int) config('learn.landing_packages.max_files');
        if ($zip->numFiles > $maxFiles) {
            $errors[] = "Package contains {$zip->numFiles} files; maximum is {$maxFiles}.";
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $rawName = (string) $stat['name'];
            $name = $this->normalizePath($rawName);

            if ($name === '' || str_ends_with($name, '/')) {
                continue;
            }

            $entries[] = $name;
            $lower = strtolower($name);
            $expandedBytes += (int) ($stat['size'] ?? 0);
            $compressedBytes += (int) ($stat['comp_size'] ?? 0);

            if ($this->isUnsafePath($rawName, $name)) {
                $errors[] = "`{$rawName}` invalid path.";
                continue;
            }

            if (isset($normalizedEntries[$name])) {
                $errors[] = "`{$name}` duplicate normalized path.";
            }

            if (isset($normalizedEntries[$lower]) && $normalizedEntries[$lower] !== $name) {
                $errors[] = "`{$name}` collides with `{$normalizedEntries[$lower]}` on case-insensitive filesystems.";
            }

            $normalizedEntries[$name] = $name;
            $normalizedEntries[$lower] = $name;

            if ($this->isLinkEntry($zip, $i)) {
                $errors[] = "`{$name}` link entries are not allowed.";
            }

            if ($this->isNestedArchive($name)) {
                $errors[] = "`{$name}` nested archives are not allowed.";
            }

            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (! $this->extensionAllowed($extension)) {
                $errors[] = "`{$name}` unsupported file type `.".($extension ?: 'none')."`.";
            }

            if ($name === 'manifest.json') {
                $manifest = json_decode($zip->getFromIndex($i), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors[] = 'manifest.json is malformed JSON.';
                }
            }

            if ($extension === 'html') {
                $this->validateHtml((string) $zip->getFromIndex($i), $name, $errors);
            }

            if ($extension === 'svg') {
                $this->validateSvg((string) $zip->getFromIndex($i), $name, $errors);
            }

            if ($extension === 'css' && $this->containsExecutableCssUrl((string) $zip->getFromIndex($i))) {
                $errors[] = "`{$name}` contains an executable CSS URL.";
            }
        }

        $zip->close();

        $maxExpanded = (int) config('learn.landing_packages.max_expanded_bytes');
        if ($expandedBytes > $maxExpanded) {
            $errors[] = "Expanded package size exceeds {$maxExpanded} bytes.";
        }

        $ratioLimit = (float) config('learn.landing_packages.max_compression_ratio', 25);
        if ($compressedBytes > 0 && ($expandedBytes / max(1, $compressedBytes)) > $ratioLimit) {
            $errors[] = 'Package compression ratio is suspiciously high.';
        }

        $checks['package size valid'] = empty($errors) || ! collect($errors)->contains(fn ($error) => str_contains($error, 'size'));
        $checks['paths safe'] = ! collect($errors)->contains(fn ($error) => str_contains($error, 'invalid path'));
        $checks['no link entries'] = ! collect($errors)->contains(fn ($error) => str_contains($error, 'link entries'));
        $checks['no nested archives'] = ! collect($errors)->contains(fn ($error) => str_contains($error, 'nested archives'));
        $checks['compression ratio sane'] = ! collect($errors)->contains(fn ($error) => str_contains($error, 'compression ratio'));
        $checks['static assets valid'] = ! collect($errors)->contains(fn ($error) => str_contains($error, 'unsupported file type'));
        $checks['no uploaded JavaScript'] = ! collect($errors)->contains(fn ($error) => str_contains($error, 'JavaScript') || str_contains($error, 'script'));
        $checks['HTML sanitized'] = ! collect($errors)->contains(fn ($error) => str_contains($error, 'event handler') || str_contains($error, 'executable URL'));
        $checks['manifest.json present'] = is_array($manifest);

        if (! is_array($manifest)) {
            $errors[] = 'manifest.json is required.';
        } else {
            $this->validateManifest($manifest, $entries, $errors, $checks);
        }

        return $this->report($manifest, $warnings, array_values(array_unique($errors)), $checks, $expandedBytes, count($entries));
    }

    public function extractPublicAssets(string $zipPath, string $targetDirectory): void
    {
        $this->validate($zipPath);

        $staging = dirname($targetDirectory).DIRECTORY_SEPARATOR.'.staging-'.Str::uuid();
        File::ensureDirectoryExists($staging);
        $zip = new ZipArchive();
        $zip->open($zipPath);

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $this->normalizePath((string) $stat['name']);

                if ($name === '' || str_ends_with($name, '/')) {
                    continue;
                }

                $destination = $staging.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $name);
                File::ensureDirectoryExists(dirname($destination));
                file_put_contents($destination, $zip->getFromIndex($i));
            }

            if (File::exists($targetDirectory)) {
                File::deleteDirectory($targetDirectory);
            }

            File::moveDirectory($staging, $targetDirectory);
        } finally {
            if (File::exists($staging)) {
                File::deleteDirectory($staging);
            }
            $zip->close();
        }
    }

    private function validateManifest(array $manifest, array $entries, array &$errors, array &$checks): void
    {
        foreach (['schemaVersion', 'name', 'version', 'sdkVersion', 'entry'] as $field) {
            if (! array_key_exists($field, $manifest) || $manifest[$field] === '') {
                $errors[] = "manifest `{$field}` is required.";
            }
        }

        $schemaSupported = in_array($manifest['schemaVersion'] ?? null, config('learn.landing_packages.supported_schema_versions'), true);
        $sdkSupported = in_array((string) ($manifest['sdkVersion'] ?? ''), config('learn.landing_packages.supported_sdk_versions'), true);
        $entry = $this->normalizePath((string) ($manifest['entry'] ?? ''));
        $entryValid = $entry !== '' && Str::startsWith($entry, 'dist/') && in_array($entry, $entries, true);

        if (! $schemaSupported) {
            $errors[] = 'manifest `schemaVersion` unsupported.';
        }

        if (! $sdkSupported) {
            $errors[] = 'manifest `sdkVersion` unsupported.';
        }

        if (! $entryValid) {
            $errors[] = 'manifest `entry` is missing or outside dist/.';
        }

        $checks['schema version supported'] = $schemaSupported;
        $checks['SDK version supported'] = $sdkSupported;
        $checks['entry point exists'] = $entryValid;
    }

    private function validateHtml(string $html, string $name, array &$errors): void
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $this->validateDomDocument($dom, $name, $errors);
    }

    private function validateSvg(string $svg, string $name, array &$errors): void
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($svg);
        libxml_clear_errors();

        if (! $loaded) {
            $errors[] = "`{$name}` is malformed SVG.";

            return;
        }

        $this->validateDomDocument($dom, $name, $errors);
    }

    private function validateDomDocument(\DOMDocument $dom, string $name, array &$errors): void
    {
        $scripts = $dom->getElementsByTagName('script');
        if ($scripts->length > 0) {
            $errors[] = "`{$name}` contains script tags; uploaded JavaScript is not allowed.";
        }

        foreach ($dom->getElementsByTagName('*') as $element) {
            if (strtolower($element->tagName) === 'base') {
                $errors[] = "`{$name}` contains a base element; platform controls landing base URLs.";
            }

            if (! $element->hasAttributes()) {
                continue;
            }

            foreach ($element->attributes as $attribute) {
                $attrName = strtolower($attribute->name);
                $value = trim(html_entity_decode($attribute->value, ENT_QUOTES | ENT_HTML5));

                if (str_starts_with($attrName, 'on')) {
                    $errors[] = "`{$name}` contains event handler attribute `{$attrName}`.";
                }

                if (in_array($attrName, ['href', 'src', 'action', 'formaction', 'xlink:href'], true) && $this->isExecutableUrl($value)) {
                    $errors[] = "`{$name}` contains executable URL in `{$attrName}`.";
                }

                if ($attrName === 'style' && $this->containsExecutableCssUrl($value)) {
                    $errors[] = "`{$name}` contains executable URL in inline style.";
                }
            }
        }
    }

    private function isExecutableUrl(string $value): bool
    {
        $normalized = strtolower(preg_replace('/[\x00-\x20]+/', '', $value) ?? '');

        return str_starts_with($normalized, 'javascript:') || str_starts_with($normalized, 'vbscript:');
    }

    private function containsExecutableCssUrl(string $css): bool
    {
        $normalized = strtolower(preg_replace('/[\x00-\x20\'"]+/', '', $css) ?? '');

        return str_contains($normalized, 'url(javascript:') || str_contains($normalized, 'url(vbscript:');
    }

    private function report(?array $manifest, array $warnings, array $errors, array $checks, int $expandedBytes = 0, int $fileCount = 0): array
    {
        return [
            'status' => $errors ? 'failed' : 'passed',
            'manifest' => $manifest,
            'warnings' => $warnings,
            'errors' => $errors,
            'expanded_bytes' => $expandedBytes,
            'file_count' => $fileCount,
            'checks' => collect($checks)->map(fn ($passed, $label) => [
                'label' => $label,
                'status' => $passed ? 'pass' : 'fail',
            ])->values()->all(),
        ];
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', trim($path));
    }

    private function isUnsafePath(string $rawName, string $name): bool
    {
        return $name === ''
            || str_contains($rawName, "\0")
            || str_contains($name, "\0")
            || str_contains($name, '//')
            || str_contains($rawName, ':')
            || str_contains($name, '../')
            || str_contains($name, '..\\')
            || str_contains($name, '/..')
            || in_array($name, ['.', '..'], true)
            || Str::startsWith($name, ['/', '\\'])
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $rawName);
    }

    private function isLinkEntry(ZipArchive $zip, int $index): bool
    {
        $opsys = 0;
        $attributes = 0;
        if (! method_exists($zip, 'getExternalAttributesIndex') || ! $zip->getExternalAttributesIndex($index, $opsys, $attributes)) {
            return false;
        }

        $unixMode = ($attributes >> 16) & 0xF000;

        return in_array($unixMode, [0xA000, 0x6000], true);
    }

    private function isNestedArchive(string $name): bool
    {
        return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz'], true);
    }

    private function extensionAllowed(string $extension): bool
    {
        if ($extension === '') {
            return false;
        }

        if (in_array($extension, config('learn.landing_packages.blocked_extensions'), true)) {
            return false;
        }

        return in_array($extension, config('learn.landing_packages.allowed_extensions'), true);
    }
}
