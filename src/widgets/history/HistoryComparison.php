<?php

declare(strict_types=1);

namespace yii\debug\widgets\history;

use PHPForge\Debug\Comparison\{PayloadDifference, SummaryMetricComparison};
use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary};

use function array_diff;
use function array_key_exists;
use function array_keys;
use function array_unique;
use function in_array;
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
    public static function fromSnapshots(DebugSnapshot $baseline, DebugSnapshot $target, array $panelLabels = []): self
    {
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
        $metrics = [];

        foreach (SummaryMetricComparison::between($baseline, $target) as $metric) {
            $metrics[] = new HistoryMetricComparison(
                label: $metric->label,
                baseline: $metric->baseline,
                target: $metric->target,
                delta: $metric->delta,
                trend: $metric->trend,
                panelId: $metric->panelId,
            );
        }

        return $metrics;
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

        $orderedIds = [
            ...$orderedIds,
            ...$extraIds,
        ];

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
}
