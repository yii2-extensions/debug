<?php

declare(strict_types=1);

namespace yii\debug\helpers;

use function sprintf;

/**
 * Formats numeric values for display in debug-panel views and toolbar chips.
 */
final class Format
{
    private const int BYTES_PER_MB = 1024 * 1024;

    /**
     * Returns a `N.NN MB` string for the given byte count, rounded to the requested precision.
     */
    public static function bytesToMb(float|int $bytes, int $precision = 2): string
    {
        return sprintf("%.{$precision}f MB", $bytes / self::BYTES_PER_MB);
    }
}
