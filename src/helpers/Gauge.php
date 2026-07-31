<?php

declare(strict_types=1);

namespace yii\debug\helpers;

use UIAwesome\Html\Phrasing\Span;

use function max;
use function min;

/**
 * Renders the inline micro-gauge rail behind numeric readouts (History Duration/Memory, Profiling Duration).
 *
 * The rail length is a percentage of the capture maximum, published as the `--yii-debug-gauge` custom property and
 * drawn entirely in CSS — no JavaScript involved.
 */
final class Gauge
{
    /**
     * Wraps a formatted readout in a micro-gauge scaled to `current / max`, clamped to `0%`..`100%`.
     *
     * Returns the readout untouched when `max` is not positive (no scale to draw against).
     *
     * @param string $value Pre-formatted readout text (for example `125 ms`).
     * @param float $current Row value in the same unit as `$max`.
     * @param float $max Capture maximum the rail is scaled against.
     */
    public static function render(string $value, float $current, float $max): string
    {
        if ($max <= 0.0) {
            return $value;
        }

        $percent = max(0.0, min(100.0, $current / $max * 100));

        return Span::tag()
            ->class('yii-debug-gauge')
            ->style(['--yii-debug-gauge' => Format::cssPercent($percent)])
            ->html(
                Span::tag()->class('yii-debug-gauge-value')->content($value),
                Span::tag()->class('yii-debug-gauge-bar')->addAttribute('aria-hidden', 'true'),
            )
            ->render();
    }
}
