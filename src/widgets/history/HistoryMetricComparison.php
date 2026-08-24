<?php

declare(strict_types=1);

namespace yii\debug\widgets\history;

/**
 * Immutable presentation data for one request-summary metric in a capture comparison.
 */
final readonly class HistoryMetricComparison
{
    /**
     * @param string $label Human-readable metric name.
     * @param string $baseline Formatted baseline value.
     * @param string $target Formatted target value.
     * @param string $delta Formatted difference from baseline to target.
     * @param string $trend Directional CSS vocabulary (`up`, `down`, or `neutral`).
     * @param string|null $panelId Related panel ID used for deep links, when applicable.
     */
    public function __construct(
        public string $label,
        public string $baseline,
        public string $target,
        public string $delta,
        public string $trend,
        public string|null $panelId = null,
    ) {}
}
