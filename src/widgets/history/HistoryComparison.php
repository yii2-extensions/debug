<?php

declare(strict_types=1);

namespace yii\debug\widgets\history;

use PHPForge\Debug\Comparison\PayloadDifference;
use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary};

use function array_diff;
use function array_key_exists;
use function array_keys;
use function array_unique;
use function in_array;
use function number_format;
use function sort;

/**
 * Builds a privacy-preserving comparison of two immutable debugger snapshots.
 *
 * Summary metrics expose already-redacted manifest data. Panel payloads are compared structurally, retaining only
 * counts of added, removed, changed, and unchanged JSON leaves instead of copying their values into the overview.
 */
final readonly class HistoryComparison
{
    /**
     * @param DebugSnapshot $baseline Baseline snapshot.
     * @param DebugSnapshot $target Target snapshot.
     * @param list<HistoryMetricComparison> $metrics Request-summary metric comparisons.
     * @param list<HistoryPanelComparison> $panels Per-panel structural comparisons.
     */
    private function __construct(
        public DebugSnapshot $baseline,
        public DebugSnapshot $target,
        public array $metrics,
        public array $panels,
    ) {}

    /**
     * Creates a comparison from two snapshots.
     *
     * @param array<string, string> $panelLabels Display names indexed by stable panel ID.
     */
    public static function fromSnapshots(
        DebugSnapshot $baseline,
        DebugSnapshot $target,
        array $panelLabels = [],
    ): self {
        return new self(
            baseline: $baseline,
            target: $target,
            metrics: self::buildMetrics($baseline->summary, $target->summary),
            panels: self::buildPanels($baseline, $target, $panelLabels),
        );
    }

    /**
     * Returns whether either summary metrics or panel payloads differ.
     */
    public function hasDifferences(): bool
    {
        foreach ($this->metrics as $metric) {
            if ($metric->delta !== 'No change') {
                return true;
            }
        }

        foreach ($this->panels as $panel) {
            if ($panel->differenceCount() > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<HistoryMetricComparison>
     */
    private static function buildMetrics(RequestSummary $baseline, RequestSummary $target): array
    {
        return [
            self::textMetric(
                'Status',
                self::status($baseline->statusCode),
                self::status($target->statusCode),
            ),
            self::textMetric(
                'Method',
                $baseline->method,
                $target->method,
            ),
            self::textMetric(
                'AJAX',
                self::yesNo($baseline->ajax),
                self::yesNo($target->ajax),
            ),
            self::nullableFloatMetric(
                'Duration',
                $baseline->processingTime,
                $target->processingTime,
                1000,
                'ms',
                'profiling',
            ),
            self::nullableFloatMetric(
                'Peak memory',
                $baseline->peakMemory,
                $target->peakMemory,
                1 / 1_048_576,
                'MB',
                'profiling',
            ),
            self::integerMetric(
                'SQL queries',
                $baseline->sqlCount,
                $target->sqlCount,
                'db',
            ),
            self::integerMetric(
                'Mail messages',
                $baseline->mailCount,
                $target->mailCount,
                'mail',
            ),
            self::integerMetric(
                'Excessive DB callers',
                $baseline->excessiveCallersCount,
                $target->excessiveCallersCount,
                'db',
            ),
        ];
    }

    /**
     * @param array<string, string> $panelLabels
     *
     * @return list<HistoryPanelComparison>
     */
    private static function buildPanels(DebugSnapshot $baseline, DebugSnapshot $target, array $panelLabels): array
    {
        $observedIds = array_unique(
            [
                ...array_keys($baseline->panels),
                ...array_keys($baseline->failures),
                ...array_keys($target->panels),
                ...array_keys($target->failures),
            ],
        );

        $orderedIds = [];

        foreach ($panelLabels as $id => $_label) {
            if (in_array($id, $observedIds, true)) {
                $orderedIds[] = $id;
            }
        }

        $extraIds = array_diff($observedIds, $orderedIds);

        sort($extraIds);

        $orderedIds = [...$orderedIds, ...$extraIds];
        $comparisons = [];

        foreach ($orderedIds as $id) {
            $baselineState = self::panelState($baseline, $id);
            $targetState = self::panelState($target, $id);

            $difference = PayloadDifference::between(
                self::panelPayload($baseline, $id),
                self::panelPayload($target, $id),
            );

            $added = $difference->added;
            $removed = $difference->removed;
            $changed = $difference->changed;
            $unchanged = $difference->unchanged;

            if ($added + $removed + $changed === 0 && $baselineState !== $targetState) {
                $changed = 1;
            }

            $comparisons[] = new HistoryPanelComparison(
                id: $id,
                label: $panelLabels[$id] ?? $id,
                baselineState: $baselineState,
                targetState: $targetState,
                added: $added,
                removed: $removed,
                changed: $changed,
                unchanged: $unchanged,
            );
        }

        return $comparisons;
    }

    private static function formatNumber(float|int $value, string $unit, int $precision): string
    {
        $formatted = number_format($value, $precision, '.', ',');

        return $unit === '' ? $formatted : "{$formatted} {$unit}";
    }

    private static function integerMetric(
        string $label,
        int $baseline,
        int $target,
        string|null $panelId = null,
    ): HistoryMetricComparison {
        return self::numericMetric($label, $baseline, $target, 1, '', $panelId, 0);
    }

    private static function nullableFloatMetric(
        string $label,
        float|int|null $baseline,
        float|int|null $target,
        float $scale,
        string $unit,
        string|null $panelId = null,
    ): HistoryMetricComparison {
        if ($baseline === null || $target === null) {
            return new HistoryMetricComparison(
                label: $label,
                baseline: $baseline === null ? 'Not captured' : self::formatNumber($baseline * $scale, $unit, 2),
                target: $target === null ? 'Not captured' : self::formatNumber($target * $scale, $unit, 2),
                delta: $baseline === $target ? 'No change' : 'Not comparable',
                trend: 'neutral',
                panelId: $panelId,
            );
        }

        return self::numericMetric($label, $baseline, $target, $scale, $unit, $panelId, 2);
    }

    private static function numericMetric(
        string $label,
        float|int $baseline,
        float|int $target,
        float $scale,
        string $unit,
        string|null $panelId,
        int $precision,
    ): HistoryMetricComparison {
        $scaledBaseline = $baseline * $scale;
        $scaledTarget = $target * $scale;
        $scaledDelta = $scaledTarget - $scaledBaseline;
        $trend = $scaledDelta > 0 ? 'up' : ($scaledDelta < 0 ? 'down' : 'neutral');

        $delta = 'No change';

        if ($scaledDelta !== 0.0) {
            $sign = $trend === 'up' ? '+' : '';
            $percentage = (float) $baseline !== 0.0
                ? " ({$sign}" . number_format((($target - $baseline) / $baseline) * 100, 1) . '%)'
                : '';
            $delta = $sign . self::formatNumber($scaledDelta, $unit, $precision) . $percentage;
        }

        return new HistoryMetricComparison(
            label: $label,
            baseline: self::formatNumber($scaledBaseline, $unit, $precision),
            target: self::formatNumber($scaledTarget, $unit, $precision),
            delta: $delta,
            trend: $trend,
            panelId: $panelId,
        );
    }

    /**
     * Returns the captured payload or failure envelope, preserving the distinction between absent and empty.
     *
     * @return array<string, mixed>|null
     */
    private static function panelPayload(DebugSnapshot $snapshot, string $id): array|null
    {
        if (isset($snapshot->failures[$id])) {
            return ['failure' => $snapshot->failures[$id]->jsonSerialize()];
        }

        return $snapshot->panels[$id] ?? null;
    }

    private static function panelState(DebugSnapshot $snapshot, string $id): string
    {
        if (array_key_exists($id, $snapshot->failures)) {
            return 'Failed';
        }

        return array_key_exists($id, $snapshot->panels) ? 'Captured' : 'Not captured';
    }

    private static function status(int $statusCode): string
    {
        return $statusCode === 0 ? 'Not captured' : (string) $statusCode;
    }

    private static function textMetric(string $label, string $baseline, string $target): HistoryMetricComparison
    {
        return new HistoryMetricComparison(
            label: $label,
            baseline: $baseline,
            target: $target,
            delta: $baseline === $target ? 'No change' : 'Changed',
            trend: 'neutral',
        );
    }

    private static function yesNo(bool $value): string
    {
        return $value ? 'Yes' : 'No';
    }
}
