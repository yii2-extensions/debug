<?php

declare(strict_types=1);

namespace yii\debug\widgets\history;

use PHPForge\Debug\Comparison\{PanelComparison, SummaryMetricComparison};
use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary};

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
        $comparisons = [];

        foreach (PanelComparison::between($baseline, $target, $panelLabels) as $panel) {
            $comparisons[] = new HistoryPanelComparison(
                id: $panel->id,
                label: $panel->label,
                baselineState: $panel->baselineState,
                targetState: $panel->targetState,
                added: $panel->added,
                removed: $panel->removed,
                changed: $panel->changed,
                unchanged: $panel->unchanged,
            );
        }

        return $comparisons;
    }
}
