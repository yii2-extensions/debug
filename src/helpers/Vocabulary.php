<?php

declare(strict_types=1);

namespace yii\debug\helpers;

use yii\log\Logger;

/**
 * Maps Yii2 logger levels to the shared semantic hue vocabulary.
 */
final class Vocabulary
{
    /**
     * Returns the level suffix (`error`, `warning`, `info`, `trace`, `profile`, or `other`) for a {@see Logger} level.
     * Profile begin/end markers share the `profile` hue.
     *
     * Usage example:
     * ```php
     * $level = \yii\debug\helpers\Vocabulary::logLevel(\yii\log\Logger::LEVEL_WARNING);
     * ```
     *
     * @param int $level Yii2 logger level.
     *
     * @return string Semantic log-level suffix.
     */
    public static function logLevel(int $level): string
    {
        return match ($level) {
            Logger::LEVEL_ERROR => 'error',
            Logger::LEVEL_WARNING => 'warning',
            Logger::LEVEL_INFO => 'info',
            Logger::LEVEL_TRACE => 'trace',
            Logger::LEVEL_PROFILE, Logger::LEVEL_PROFILE_BEGIN, Logger::LEVEL_PROFILE_END => 'profile',
            default => 'other',
        };
    }

}
