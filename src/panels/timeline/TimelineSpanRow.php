<?php

declare(strict_types=1);

namespace yii\debug\panels\timeline;

use function abs;
use function is_array;
use function is_numeric;
use function is_string;
use function max;
use function rtrim;
use function sprintf;
use function str_contains;

/**
 * Typed view-model for one span row in the Timeline panel chart.
 *
 * Encapsulates the loose `array<string, mixed>` shape produced by {@see \yii\debug\models\timeline\DataProvider} into a
 * `final readonly` DTO so the renderer stays free of {@see is_array()} / {@see is_numeric()} narrowing on every cell
 * access.
 *
 * The category → CSS-variant mapping and the tooltip composition live here so the renderer ends up purely formatting.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final readonly class TimelineSpanRow
{
    public function __construct(
        /**
         * Span category ({@see \yii\db\Command::query}, {@see \yii\base\Application::handleRequest}, ...).
         *
         * Empty when not captured.
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
         * Bar 'left' offset on the chart, as a percentage string ('12.5') consumed by inline `style="left:<X>%"`.
         */
        public string $cssLeft,
        /**
         * Bar 'width' on the chart, as a percentage string with a '0.4%' floor so single-millisecond spans stay
         * visible.
         */
        public string $cssWidth,
        /**
         * CSS variant token ('info' / 'success' / 'warning' / 'danger' / 'muted') derived from '$category'.
         */
        public string $variant,
        /**
         * Pre-formatted `<title>` tooltip text combining category/info, duration, peak memory and memory delta.
         */
        public string $tooltip,
    ) {}

    /**
     * Narrows the loose array shape into a typed row. Computes the CSS variant, the tooltip text and clamps the bar
     * width to the visible-floor ('0.4%').
     *
     * @param array<string, mixed> $row
     */
    public static function from(array $row): self
    {
        $category = self::asString($row['category'] ?? '');
        $duration = self::asFloat($row['duration'] ?? 0.0);
        $memoryBytes = self::asFloat($row['memory'] ?? 0.0);
        $memoryDiff = self::asFloat($row['memoryDiff'] ?? 0.0);

        $css = is_array($row['css'] ?? null) ? $row['css'] : [];

        $cssLeft = self::numberToString($css['left'] ?? 0);
        $cssWidth = self::numberToString(max(self::asFloat($css['width'] ?? 0), 0.4));

        $info = self::asString($row['info'] ?? '');

        $tooltipHeading = $info !== '' ? $info : $category;

        return new self(
            category: $category,
            duration: $duration,
            depth: self::asInt($row['child'] ?? 0),
            cssLeft: $cssLeft,
            cssWidth: $cssWidth,
            variant: self::variantOf($category),
            tooltip: self::buildTooltip($tooltipHeading, $duration, $memoryBytes, $memoryDiff),
        );
    }

    private static function asFloat(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private static function asInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function asString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * Composes the multi-line tooltip text. Memory delta is omitted when zero — matches the legacy view's
     * `!empty($model['memoryDiff'])` guard so capture snapshots stay byte-equivalent.
     */
    private static function buildTooltip(string $heading, float $duration, float $memoryBytes, float $memoryDiff): string
    {
        $memoryDelta = '';

        if ($memoryDiff !== 0.0) {
            $memoryDelta = sprintf(
                ' (%s%.2f MB)',
                $memoryDiff > 0 ? '+' : '−',
                abs($memoryDiff) / 1048576,
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
     * Formats a numeric percentage value with a fixed three-decimal precision, dropping the trailing zeros so common
     * round values render as '12' rather than '12.000'.
     *
     * Matches the legacy {@see \yii\helpers\StringHelper::normalizeNumber()} output for the values the timeline
     * produces.
     */
    private static function numberToString(mixed $value): string
    {
        $float = self::asFloat($value);

        $rendered = sprintf('%.3f', $float);
        $rendered = rtrim($rendered, '0');

        return rtrim($rendered, '.');
    }

    /**
     * Maps the span category to a CSS variant. Categories the matcher does not recognise fall back to 'muted' so
     * unknown providers render in the neutral track styling.
     */
    private static function variantOf(string $category): string
    {
        if ($category === '') {
            return 'muted';
        }

        if (str_contains($category, 'db\\') || str_contains($category, 'Command')) {
            return 'info';
        }

        if (str_contains($category, 'cache') || str_contains($category, 'Cache')) {
            return 'success';
        }

        if (
            str_contains($category, 'View')
            || str_contains($category, 'render')
            || str_contains($category, 'twig')
        ) {
            return 'warning';
        }

        if (str_contains($category, 'mail') || str_contains($category, 'queue')) {
            return 'danger';
        }

        return 'muted';
    }
}
