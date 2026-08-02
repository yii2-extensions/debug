<?php

declare(strict_types=1);

namespace yii\debug\helpers;

use function rtrim;
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

    /**
     * Returns a CSS percentage (`42%`, `33.333%`) with at most three decimals and trailing zeros trimmed.
     */
    public static function cssPercent(float $value): string
    {
        $rendered = sprintf('%.3f', $value);
        $rendered = rtrim($rendered, '0');
        $rendered = rtrim($rendered, '.');

        return "{$rendered}%";
    }
}
