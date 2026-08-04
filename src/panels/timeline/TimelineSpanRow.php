<?php

declare(strict_types=1);

namespace yii\debug\panels\timeline;

use yii\debug\panels\profile\ProfileRow;

use function max;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_replace;

/**
 * Typed view-model for one span row in the Timeline panel chart.
 *
 * The category → CSS-variant mapping and the tooltip composition live here, so the renderer ends up purely formatting.
 */
final readonly class TimelineSpanRow
{
    public function __construct(
        /**
         * Span category ({@see \yii\db\Command::query}, {@see \yii\base\Application::handleRequest}, ...), or `''`
         * when not captured.
         */
        public string $category,
        /**
         * Span duration in milliseconds.
         */
        public float $duration,
        /**
         * Nesting depth in the call tree; the renderer indents the row by this multiplier.
         */
        public int $depth,
        /**
         * Bar `left` offset on the chart, as a percentage string (for example, `12.5`) consumed by inline
         * `style="left:<X>%"`.
         */
        public string $cssLeft,
        /**
         * Bar `width` on the chart, as a percentage string with a `0.4%` floor so single-millisecond spans stay
         * visible.
         */
        public string $cssWidth,
        /**
         * CSS variant token (`app` / `db` / `view` / `cache` / `mail` / `queue` / `other`) derived from `$category`.
         */
        public string $variant,
        /**
         * Pre-formatted `<title>` tooltip text combining category/info, duration, peak memory, and memory delta.
         */
        public string $tooltip,
    ) {}

    /**
     * Builds the view row from a captured profile block and its computed chart geometry.
     *
     * Derives the CSS variant and the tooltip text, and clamps the bar width to the visible floor (`0.4%`).
     *
     * The indent reuses the nesting level the profiler already recorded, so the chart and the Profiling grid agree on
     * the call tree.
     *
     * @param ProfileRow $row Captured profile block.
     * @param float $left Bar left offset as a percentage of the request duration.
     * @param float $width Bar width as a percentage of the request duration.
     */
    public static function from(ProfileRow $row, float $left, float $width): self
    {
        $heading = $row->info !== '' ? $row->info : $row->category;

        return new self(
            category: $row->category,
            duration: $row->duration,
            depth: $row->level,
            cssLeft: self::numberToString($left),
            cssWidth: self::numberToString(max($width, 0.4)),
            variant: self::variantOf($row->category),
            tooltip: self::buildTooltip($heading, $row->duration, (float) $row->memory, (float) $row->memoryDiff),
        );
    }

    /**
     * Composes the multi-line tooltip text.
     *
     * Memory delta is omitted when zero, matching the legacy view's `!empty($model['memoryDiff'])` guard so capture
     * snapshots stay byte-equivalent.
     */
    private static function buildTooltip(string $heading, float $duration, float $memoryBytes, float $memoryDiff): string
    {
        $memoryDelta = '';

        if ($memoryDiff !== 0.0) {
            $memoryDelta = str_replace(
                '-',
                '−',
                sprintf(' (%+.2f MB)', $memoryDiff / 1048576),
            );
        }

        return sprintf(
            "%s\n%.3f ms · %.2f MB%s",
            $heading,
            $duration,
            $memoryBytes / 1048576,
            $memoryDelta,
        );
    }

    /**
     * Formats a numeric percentage value with three-decimal precision, dropping trailing zeros so common round values
     * render as `12` rather than `12.000`.
     *
     * Matches the legacy {@see \yii\helpers\StringHelper::normalizeNumber()} output for the values the timeline
     * produces.
     */
    private static function numberToString(float $value): string
    {
        $rendered = sprintf('%.3f', $value);
        $rendered = rtrim($rendered, '0');

        return rtrim($rendered, '.');
    }

    /**
     * Maps the span category to its domain variant from the fixed timeline vocabulary.
     *
     * Categories the matcher does not recognize fall back to `other`, so unknown providers render in the neutral
     * track styling.
     */
    private static function variantOf(string $category): string
    {
        if (str_contains($category, 'db\\') || str_contains($category, 'Command')) {
            return 'db';
        }

        if (str_contains($category, 'cache') || str_contains($category, 'Cache')) {
            return 'cache';
        }

        if (
            str_contains($category, 'View')
            || str_contains($category, 'render')
            || str_contains($category, 'twig')
        ) {
            return 'view';
        }

        if (str_contains($category, 'mail') || str_contains($category, 'Mail')) {
            return 'mail';
        }

        if (str_contains($category, 'queue') || str_contains($category, 'Queue')) {
            return 'queue';
        }

        if (
            str_contains($category, 'Application')
            || str_contains($category, 'Controller')
            || str_contains($category, 'controllers')
            || str_contains($category, 'runAction')
        ) {
            return 'app';
        }

        return 'other';
    }
}
