<?php

declare(strict_types=1);

namespace yii\debug\widgets\history;

/**
 * Immutable structural-difference summary for one panel across two captured requests.
 *
 * Counts describe JSON leaf paths only. Captured values are deliberately excluded so the comparison overview cannot
 * reveal data that the individual panels keep behind their own presentation and redaction rules.
 */
final readonly class HistoryPanelComparison
{
    /**
     * @param string $id Stable panel ID.
     * @param string $label Panel display name.
     * @param string $baselineState Baseline state (`Captured`, `Failed`, or `Not captured`).
     * @param string $targetState Target state (`Captured`, `Failed`, or `Not captured`).
     * @param int $added JSON leaf paths present only in the target.
     * @param int $removed JSON leaf paths present only in the baseline.
     * @param int $changed Shared JSON leaf paths whose typed values changed.
     * @param int $unchanged Shared JSON leaf paths whose typed values stayed equal.
     */
    public function __construct(
        public string $id,
        public string $label,
        public string $baselineState,
        public string $targetState,
        public int $added,
        public int $removed,
        public int $changed,
        public int $unchanged,
    ) {}

    /**
     * Returns the total number of structural differences detected for the panel.
     */
    public function differenceCount(): int
    {
        return $this->added + $this->removed + $this->changed;
    }
}
