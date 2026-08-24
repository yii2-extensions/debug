<?php

declare(strict_types=1);

namespace yii\debug\widgets\history;

use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary};

use function array_diff;
use function array_diff_key;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_unique;
use function array_values;
use function count;
use function get_debug_type;
use function hash;
use function in_array;
use function is_array;
use function json_encode;
use function number_format;
use function sort;
use function str_replace;

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
            if ($panel->differenceCount() > 0 || $panel->baselineState !== $panel->targetState) {
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
            self::textMetric('Status', self::status($baseline->statusCode), self::status($target->statusCode)),
            self::textMetric('Method', $baseline->method, $target->method),
            self::textMetric('AJAX', self::yesNo($baseline->ajax), self::yesNo($target->ajax)),
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
            self::integerMetric('SQL queries', $baseline->sqlCount, $target->sqlCount, 'db'),
            self::integerMetric('Mail messages', $baseline->mailCount, $target->mailCount, 'mail'),
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
    private static function buildPanels(
        DebugSnapshot $baseline,
        DebugSnapshot $target,
        array $panelLabels,
    ): array {
        $observedIds = array_unique(
            array_merge(
                array_keys($baseline->panels),
                array_keys($baseline->failures),
                array_keys($target->panels),
                array_keys($target->failures),
            ),
        );

        $orderedIds = [];

        foreach ($panelLabels as $id => $_label) {
            if (in_array($id, $observedIds, true)) {
                $orderedIds[] = $id;
            }
        }

        $extraIds = array_values(array_diff($observedIds, $orderedIds));

        sort($extraIds);

        $orderedIds = [...$orderedIds, ...$extraIds];
        $comparisons = [];

        foreach ($orderedIds as $id) {
            $baselineState = self::panelState($baseline, $id);
            $targetState = self::panelState($target, $id);
            $baselineLeaves = self::panelLeaves($baseline, $id);
            $targetLeaves = self::panelLeaves($target, $id);

            $added = count(array_diff_key($targetLeaves, $baselineLeaves));
            $removed = count(array_diff_key($baselineLeaves, $targetLeaves));
            $changed = 0;
            $unchanged = 0;

            foreach ($baselineLeaves as $path => $baselineValue) {
                if (!array_key_exists($path, $targetLeaves)) {
                    continue;
                }

                if ($baselineValue === $targetLeaves[$path]) {
                    ++$unchanged;
                } else {
                    ++$changed;
                }
            }

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

    /**
     * Flattens a panel payload into typed JSON-leaf fingerprints keyed by JSON Pointer-like paths.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, string>
     */
    private static function flatten(array $payload): array
    {
        $leaves = [];

        self::flattenValue($payload, '$', $leaves);

        return $leaves;
    }

    /**
     * @param array<string, string> $leaves
     */
    private static function flattenValue(mixed $value, string $path, array &$leaves): void
    {
        if (is_array($value)) {
            if ($value === []) {
                $leaves[$path] = 'array:[]';

                return;
            }

            foreach ($value as $key => $child) {
                $segment = str_replace(['~', '/'], ['~0', '~1'], (string) $key);

                self::flattenValue($child, "{$path}/{$segment}", $leaves);
            }

            return;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $fingerprint = get_debug_type($value) . ':' . ($encoded === false ? '' : $encoded);

        $leaves[$path] = hash('sha256', $fingerprint);
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

        if ($scaledDelta === 0.0) {
            $delta = 'No change';
        } else {
            $sign = $scaledDelta > 0 ? '+' : '';
            $percentage = $baseline !== 0
                ? ' (' . ($scaledDelta > 0 ? '+' : '') . number_format((($target - $baseline) / $baseline) * 100, 1) . '%)'
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
     * Returns typed fingerprints for the captured panel payload or its isolated failure envelope.
     *
     * @return array<string, string>
     */
    private static function panelLeaves(DebugSnapshot $snapshot, string $id): array
    {
        if (isset($snapshot->failures[$id])) {
            return self::flatten(['failure' => $snapshot->failures[$id]->jsonSerialize()]);
        }

        return isset($snapshot->panels[$id]) ? self::flatten($snapshot->panels[$id]) : [];
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
