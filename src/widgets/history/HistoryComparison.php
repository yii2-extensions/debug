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
    private function __construct(private SnapshotComparison $comparison, public array $metrics, public array $panels)
    {
        $this->baseline = $comparison->baseline;
        $this->target = $comparison->target;
    }

    /**
     * Creates a comparison from two snapshots.
     *
     * @param DebugSnapshot $baseline Capture used as the reference side of the comparison.
     * @param DebugSnapshot $target Capture compared against the baseline.
     * @param array<string, string> $panelLabels Display names indexed by stable panel ID.
     *
     * @return self Comparison holding the metric and panel differences between both captures.
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
     *
     * @return bool `true` when metrics or panel payloads differ; `false` when the captures match.
     */
    public function hasDifferences(): bool
    {
        return $this->comparison->hasDifferences();
    }

    /**
     * @param list<SummaryMetricComparison> $metrics Summary metric comparisons to transform into presentation models.
     *
     * @return list<HistoryMetricComparison> Presentation models derived from the summary metric comparisons.
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
     * @param list<PanelComparison> $panels Per-panel structural comparisons to transform into presentation models.
     *
     * @return list<HistoryPanelComparison> Presentation models derived from the per-panel structural comparisons.
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
