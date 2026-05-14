<?php

declare(strict_types=1);

namespace yii\debug\helpers;

use UIAwesome\Html\Svg\Svg;

use function dirname;
use function is_file;

/**
 * Renders SVG icons bundled with the debug extension via {@see Svg::tag()}.
 *
 * Icons live as `.svg` files under `src/assets/svg/` and are looked up by name (without extension). Results are cached
 * in-memory for the request, so repeated lookups (panel icons, chevrons, etc.) do not re-read the file or re-run the
 * libxml-based sanitization process.
 */
final class Icon
{
    /**
     * @var array<string, string> In-memory cache of rendered SVG markup, indexed by icon name.
     */
    private static array $cache = [];

    /**
     * Returns the rendered SVG markup for the given icon name, or an empty string when the file does not exist.
     *
     * @param string $name Icon basename without the `.svg` extension (for example, `chevron-down`).
     *
     * @return string Sanitized SVG markup, or `''` when the source file is missing.
     */
    public static function render(string $name): string
    {
        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }

        $path = dirname(__DIR__) . '/assets/svg/' . $name . '.svg';

        if (!is_file($path)) {
            return self::$cache[$name] = '';
        }

        return self::$cache[$name] = Svg::tag()->filePath($path)->render();
    }
}
