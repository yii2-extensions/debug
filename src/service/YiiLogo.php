<?php

declare(strict_types=1);

namespace yii\debug\service;

use PHPForge\Debug\Helper\Icon;
use RuntimeException;
use yii\debug\exception\Message;

use function base64_encode;

/**
 * Serves the Yii mark as a data URI, reading the shared frontend asset once and caching the result.
 */
final class YiiLogo
{
    /**
     * Cached `data:image/svg+xml;base64` URI of the Yii logo, populated lazily by {@see dataUri()}.
     */
    private static string|null $logo = null;

    /**
     * Returns the Yii logo as a data URI ready to drop into `<img src="…">` or `<link rel="icon">`.
     *
     * Reads the shared frontend file so every framework adapter renders the same Yii mark.
     *
     * @throws RuntimeException when the packaged logo cannot be read.
     *
     * @return string Logo as a data URI.
     */
    public static function dataUri(): string
    {
        if (self::$logo === null) {
            $svg = Icon::render('yii');

            if ($svg === '') {
                throw new RuntimeException(
                    Message::YII_LOGO_UNREADABLE->getMessage(),
                );
            }

            self::$logo = 'data:image/svg+xml;base64,' . base64_encode($svg);
        }

        return self::$logo;
    }

    /**
     * Drops the cached URI, so the next read composes it from the packaged asset again.
     */
    public static function reset(): void
    {
        self::$logo = null;
    }

    /**
     * Sets the logo data URI returned by {@see dataUri()}.
     *
     * @param string $logo Logo as a data URI.
     */
    public static function set(string $logo): void
    {
        self::$logo = $logo;
    }
}
