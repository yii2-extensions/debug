<?php

declare(strict_types=1);

namespace yii\debug\actions;

use PHPForge\Debug\Storage\DebugSnapshot;
use yii\debug\exception\Message;
use yii\debug\widgets\history\HistoryComparison;
use yii\web\NotFoundHttpException;

use function array_keys;
use function count;

/**
 * Compares request summaries and panel payload structure across two captured debugger snapshots.
 */
final class CompareAction extends Action
{
    /**
     * Runs the comparison page.
     *
     * When tags are omitted, the newest capture is selected as the target and the preceding capture as the baseline.
     * Unknown or rotated tags fail explicitly instead of silently selecting a different request.
     *
     * @param string|null $baseline Baseline request tag.
     * @param string|null $target Target request tag.
     *
     * @throws NotFoundHttpException When fewer than two captures exist or either requested snapshot is unavailable.
     *
     * @return string Rendered comparison page.
     */
    public function run(string|null $baseline = null, string|null $target = null): string
    {
        $manifest = $this->getManifest();
        $tags = array_keys($manifest);

        if (count($tags) < 2 && ($baseline === null || $target === null)) {
            throw new NotFoundHttpException(
                'At least two captured requests are required for comparison.',
            );
        }

        $target ??= $tags[0] ?? null;
        $baseline ??= $tags[1] ?? null;

        if ($baseline === null || $target === null || !isset($manifest[$baseline], $manifest[$target])) {
            throw new NotFoundHttpException(
                Message::DEBUG_DATA_NOT_FOUND->getMessage($baseline ?? $target ?? ''),
            );
        }

        $baselineSnapshot = $this->getLogTarget()->loadSnapshot($baseline);
        $targetSnapshot = $this->getLogTarget()->loadSnapshot($target);

        if (!$baselineSnapshot instanceof DebugSnapshot || !$targetSnapshot instanceof DebugSnapshot) {
            throw new NotFoundHttpException(
                Message::DEBUG_DATA_NOT_FOUND->getMessage(
                    !($baselineSnapshot instanceof DebugSnapshot) ? $baseline : $target,
                ),
            );
        }

        $module = $this->getDebugModule();
        $panelLabels = [];

        foreach ($module->panels as $id => $panel) {
            $panelLabels[$id] = $panel->getName() !== '' ? $panel->getName() : $id;
        }

        $comparison = HistoryComparison::fromSnapshots($baselineSnapshot, $targetSnapshot, $panelLabels);

        $this->loadData($target);
        $this->prepareIndexShell($manifest, $target);

        return $this->render(
            'compare',
            [
                'baseline' => $baseline,
                'comparison' => $comparison,
                'manifest' => $manifest,
                'target' => $target,
            ],
        );
    }
}
