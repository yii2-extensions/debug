<?php

declare(strict_types=1);

namespace yii\debug\widgets\history;

use PHPForge\Debug\Comparison\{PanelComparison, SnapshotComparison, SummaryMetricComparison};
use PHPForge\Debug\Storage\DebugSnapshot;

/**
 * Builds a privacy-preserving comparison of two immutable debugger snapshots.
 *
 * Summary metrics expose already-redacted manifest data. Panel payloads are compared structurally, retaining only
 * counts of added, removed, changed, and unchanged JSON leaves instead of copying their values into the overview.
 */
final readonly class HistoryComparison
{
    /**
     * Baseline snapshot.
     */
    public DebugSnapshot $baseline;
    /**
     * Target snapshot.
     */
    public DebugSnapshot $target;

    /**
     * @param SnapshotComparison $comparison Shared comparison the presentation models are derived from.
     * @param list<HistoryMetricComparison> $metrics Request-summary metric comparisons.
     * @param list<HistoryPanelComparison> $panels Per-panel structural comparisons.
     */
    private function __construct(
        private SnapshotComparison $comparison,
        public array $metrics,
        public array $panels,
    ) {
        $this->baseline = $comparison->baseline;
        $this->target = $comparison->target;
    }

    /**
     * Creates a comparison from two snapshots.
     *
     * @param array<string, string> $panelLabels Display names indexed by stable panel ID.
     */
    public static function fromSnapshots(DebugSnapshot $baseline, DebugSnapshot $target, array $panelLabels = []): self
    {
        $comparison = SnapshotComparison::between($baseline, $target, $panelLabels);

        return new self(
            comparison: $comparison,
            metrics: self::buildMetrics($comparison->metrics),
            panels: self::buildPanels($comparison->panels),
        );
    }

    /**
     * Returns whether either summary metrics or panel payloads differ.
     */
    public function hasDifferences(): bool
    {
        return $this->comparison->hasDifferences();
    }

    /**
     * @param list<SummaryMetricComparison> $metrics
     *
     * @return list<HistoryMetricComparison>
     */
    private static function buildMetrics(array $metrics): array
    {
        $presentation = [];

        foreach ($metrics as $metric) {
            $presentation[] = new HistoryMetricComparison(
                label: $metric->label,
                baseline: $metric->baseline,
                target: $metric->target,
                delta: $metric->delta,
                trend: $metric->trend,
                panelId: $metric->panelId,
            );
        }

        return $presentation;
    }

    /**
     * @param list<PanelComparison> $panels
     *
     * @return list<HistoryPanelComparison>
     */
    private static function buildPanels(array $panels): array
    {
        $presentation = [];

        foreach ($panels as $panel) {
            $presentation[] = new HistoryPanelComparison(
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

        return $presentation;
    }
}
