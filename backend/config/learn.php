<?php

return [
    'guest_access_days' => env('GUEST_ACCESS_DAYS', 30),
    'download_url_minutes' => env('DOWNLOAD_URL_MINUTES', 10),
    'resource_library' => [
        'max_upload_bytes' => env('RESOURCE_MAX_UPLOAD_BYTES', 100 * 1024 * 1024),
    ],
    'landing_packages' => [
        'max_zip_bytes' => env('LANDING_PACKAGE_MAX_ZIP_BYTES', 50 * 1024 * 1024),
        'max_expanded_bytes' => env('LANDING_PACKAGE_MAX_EXPANDED_BYTES', 150 * 1024 * 1024),
        'max_compression_ratio' => env('LANDING_PACKAGE_MAX_COMPRESSION_RATIO', 25),
        'max_files' => env('LANDING_PACKAGE_MAX_FILES', 750),
        'supported_schema_versions' => [2],
        'supported_sdk_versions' => ['2'],
        'allowed_extensions' => [
            'html', 'css', 'json',
            'png', 'jpg', 'jpeg', 'webp', 'avif', 'svg',
            'gif', 'ico', 'mp4', 'webm', 'woff', 'woff2', 'ttf',
        ],
        'blocked_extensions' => [
            'php', 'phtml', 'phar', 'py', 'rb', 'sh', 'bash', 'zsh', 'fish',
            'bat', 'cmd', 'ps1', 'exe', 'dll', 'so', 'jar', 'com', 'scr',
            'msi', 'cgi', 'pl', 'asp', 'aspx', 'js', 'mjs', 'cjs',
        ],
    ],
];
