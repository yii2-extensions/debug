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
     * Request-summary metric comparisons in canonical history order.
     *
     * @var list<SummaryMetricComparison>
     */
    public array $metrics;
    /**
     * Per-panel structural comparisons in configured display order.
     *
     * @var list<PanelComparison>
     */
    public array $panels;
    /**
     * Target snapshot.
     */
    public DebugSnapshot $target;

    /**
     * @param SnapshotComparison $comparison Shared comparison the view reads.
     */
    private function __construct(private SnapshotComparison $comparison)
    {
        $this->baseline = $comparison->baseline;
        $this->metrics = $comparison->metrics;
        $this->panels = $comparison->panels;
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
        return new self(SnapshotComparison::between($baseline, $target, $panelLabels));
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
}
