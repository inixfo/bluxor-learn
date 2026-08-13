<?php

namespace App\Services;

class PipraPayStatus
{
    private const MAP = [
        'initiated' => 'initiated',
        'created' => 'initiated',
        'pending' => 'pending',
        'processing' => 'pending',
        'completed' => 'completed',
        'complete' => 'completed',
        'paid' => 'completed',
        'success' => 'completed',
        'successful' => 'completed',
        'failed' => 'failed',
        'failure' => 'failed',
        'declined' => 'failed',
        'cancelled' => 'cancelled',
        'canceled' => 'cancelled',
        'refunded' => 'refunded',
    ];

    public static function normalize(?string $status): string
    {
        $key = strtolower(trim((string) $status));

        return self::MAP[$key] ?? 'unknown';
    }

    public static function isCompleted(?string $status): bool
    {
        return self::normalize($status) === 'completed';
    }

    public static function isRefunded(?string $status): bool
    {
        return self::normalize($status) === 'refunded';
    }

    public static function isRefundSuccess(?string $status, mixed $successFlag = null): bool
    {
        return self::isRefunded($status)
            || self::isCompleted($status)
            || $successFlag === true
            || strtolower((string) $successFlag) === 'true';
    }
}
