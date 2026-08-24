<?php

declare(strict_types=1);

use PHPForge\Debug\Storage\RequestSummary;
use UIAwesome\Html\Heading\H1;
use yii\debug\Module;
use yii\debug\widgets\history\{HistoryComparison, HistoryPanelComparison};
use yii\helpers\{Html, Url};
use yii\web\View;

/**
 * @var string $baseline Selected baseline tag.
 * @var HistoryComparison $comparison Typed comparison data.
 * @var array<string, RequestSummary> $manifest Debug manifest, newest first.
 * @var string $target Selected target tag.
 * @var View $this View component instance.
 */
$this->title = 'Compare captures';

$baselineSummary = $comparison->baseline->summary;
$targetSummary = $comparison->target->summary;

$captureUrl = static fn(string $tag, string $panel = 'request'): string => Url::to(
    Module::route('view', ['panel' => $panel, 'tag' => $tag]),
);

$stateBadge = static function (string $state): string {
    $variant = match ($state) {
        'Captured' => 'success',
        'Failed' => 'danger',
        default => 'muted',
    };

    return Html::tag('span', Html::encode($state), ['class' => "yii-debug-badge yii-debug-badge-{$variant}"]);
};

$panelLink = static function (HistoryPanelComparison $panel, string $tag, string $state) use ($captureUrl): string {
    if ($state === 'Not captured') {
        return Html::tag('span', '—', ['class' => 'yii-debug-not-set']);
    }

    return Html::a('Open panel', $captureUrl($tag, $panel->id), ['class' => 'yii-debug-btn yii-debug-btn-ghost yii-debug-btn-sm']);
};
?>
<?= H1::tag()->class('yii-debug-hero-title')->content('Compare captures') ?>

<section class="yii-debug-section" aria-labelledby="yii-debug-compare-selection">
    <h2 class="yii-debug-section-title" id="yii-debug-compare-selection">
        <span class="yii-debug-section-mark">01</span>
        Selection
    </h2>
    <?= $this->render(
        '_compare-form',
        [
            'baseline' => $baseline,
            'manifest' => $manifest,
            'target' => $target,
        ],
    ) ?>
</section>

<section class="yii-debug-section" aria-labelledby="yii-debug-compare-overview-title">
    <h2 class="yii-debug-section-title" id="yii-debug-compare-overview-title">
        <span class="yii-debug-section-mark">02</span>
        Capture overview
    </h2>
    <div class="yii-debug-compare-overview">
        <article class="yii-debug-readout-card">
            <span class="yii-debug-readout-label">Baseline</span>
            <?= Html::a(
                Html::encode($baselineSummary->tag),
                $captureUrl($baselineSummary->tag),
                ['class' => 'yii-debug-readout-value'],
            ) ?>
            <span class="yii-debug-readout-meta">
                <?= Html::encode("{$baselineSummary->method} · {$baselineSummary->statusCode}") ?>
            </span>
            <span class="yii-debug-muted" title="<?= Html::encode($baselineSummary->url) ?>">
                <?= Html::encode($baselineSummary->url) ?>
            </span>
        </article>
        <article class="yii-debug-readout-card">
            <span class="yii-debug-readout-label">Target</span>
            <?= Html::a(
                Html::encode($targetSummary->tag),
                $captureUrl($targetSummary->tag),
                ['class' => 'yii-debug-readout-value'],
            ) ?>
            <span class="yii-debug-readout-meta">
                <?= Html::encode("{$targetSummary->method} · {$targetSummary->statusCode}") ?>
            </span>
            <span class="yii-debug-muted" title="<?= Html::encode($targetSummary->url) ?>">
                <?= Html::encode($targetSummary->url) ?>
            </span>
        </article>
        <article class="yii-debug-readout-card">
            <span class="yii-debug-readout-label">Result</span>
            <span class="yii-debug-readout-value">
                <?= $comparison->hasDifferences() ? 'Changed' : 'Identical' ?>
            </span>
            <span class="yii-debug-readout-meta">Summary and panel structure</span>
        </article>
    </div>
</section>

<section class="yii-debug-section" aria-labelledby="yii-debug-compare-metrics-title">
    <h2 class="yii-debug-section-title" id="yii-debug-compare-metrics-title">
        <span class="yii-debug-section-mark">03</span>
        Request metrics
    </h2>
    <div class="yii-debug-table-wrap">
        <table class="yii-debug-table yii-debug-compare-grid">
            <caption class="yii-debug-sr-only">Request summary comparison</caption>
            <thead>
            <tr>
                <th scope="col">Metric</th>
                <th scope="col">Baseline</th>
                <th scope="col">Target</th>
                <th scope="col">Delta</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($comparison->metrics as $metric): ?>
                <tr>
                    <th scope="row">
                        <?php if ($metric->panelId === null): ?>
                            <?= Html::encode($metric->label) ?>
                        <?php else: ?>
                            <?= Html::a(
                                Html::encode($metric->label),
                                $captureUrl($target, $metric->panelId),
                            ) ?>
                        <?php endif ?>
                    </th>
                    <td class="yii-debug-cell-mono"><?= Html::encode($metric->baseline) ?></td>
                    <td class="yii-debug-cell-mono"><?= Html::encode($metric->target) ?></td>
                    <td>
                        <span class="yii-debug-delta-<?= Html::encode($metric->trend) ?>">
                            <?= Html::encode($metric->delta) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</section>

<section class="yii-debug-section" aria-labelledby="yii-debug-compare-panels-title">
    <h2 class="yii-debug-section-title" id="yii-debug-compare-panels-title">
        <span class="yii-debug-section-mark">04</span>
        Panel structure
        <span class="yii-debug-section-count"><?= count($comparison->panels) ?></span>
    </h2>
    <p class="yii-debug-muted">
        Counts compare typed JSON leaf paths without rendering captured values. Open either panel for its redacted detail.
    </p>
    <div class="yii-debug-table-wrap">
        <table class="yii-debug-table yii-debug-compare-grid">
            <caption class="yii-debug-sr-only">Panel structure comparison</caption>
            <thead>
            <tr>
                <th scope="col">Panel</th>
                <th scope="col">Baseline</th>
                <th scope="col">Target</th>
                <th scope="col">Added</th>
                <th scope="col">Removed</th>
                <th scope="col">Changed</th>
                <th scope="col">Unchanged</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($comparison->panels as $panel): ?>
                <tr>
                    <th scope="row"><?= Html::encode($panel->label) ?></th>
                    <td>
                        <?= $stateBadge($panel->baselineState) ?>
                        <?= $panelLink($panel, $baseline, $panel->baselineState) ?>
                    </td>
                    <td>
                        <?= $stateBadge($panel->targetState) ?>
                        <?= $panelLink($panel, $target, $panel->targetState) ?>
                    </td>
                    <td class="yii-debug-cell-numeric"><?= $panel->added ?></td>
                    <td class="yii-debug-cell-numeric"><?= $panel->removed ?></td>
                    <td class="yii-debug-cell-numeric"><?= $panel->changed ?></td>
                    <td class="yii-debug-cell-numeric"><?= $panel->unchanged ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</section>
