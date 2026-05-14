<?php

declare(strict_types=1);

namespace yii\debug\helpers;

use function abs;
use function crc32;
use function strtolower;

/**
 * Derives stable, deterministic avatar colours from arbitrary identifying strings.
 *
 * Two debug-panel renderers (mail and queue) display a colored circle next to each item; both used to compute the hue
 * with the same `abs(crc32(strtolower(...))) % 360` formula. This helper centralises that derivation so the colour
 * stays consistent across renderers.
 */
final class Avatar
{
    /**
     * Fallback hue used when `$seed` is empty.
     */
    private const int DEFAULT_HUE = 210;

    /**
     * Returns a stable hue (`0..359`) for the given seed, or {@see self::DEFAULT_HUE} when the seed is empty.
     */
    public static function hueFor(string $seed): int
    {
        if ($seed === '') {
            return self::DEFAULT_HUE;
        }

        return abs(crc32(strtolower($seed))) % 360;
    }
}
