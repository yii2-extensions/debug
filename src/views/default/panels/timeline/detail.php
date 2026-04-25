<?php

declare(strict_types=1);

use yii\debug\TimelineAsset;
use yii\helpers\Html;
use yii\helpers\StringHelper;
use yii\helpers\Url;

/** @var yii\debug\panels\TimelinePanel $panel */
/** @var \yii\debug\models\timeline\Search $searchModel */
/** @var \yii\debug\models\timeline\DataProvider $dataProvider */

TimelineAsset::register($this);

$totalDuration = (float) $panel->getDuration();
$models = $dataProvider->models;
$hasData = $models !== [];
$peakMemoryMB = sprintf('%.2f MB', (float) $panel->memory / 1048576);

// Category → color variant: deterministic mapping so the same span keeps the same hue
// across renders, while still using design-system tokens (no hardcoded greens).
$categoryVariant = static function (string $category): string {
    if ($category === '') {
        return 'muted';
    }
    if (str_contains($category, 'db\\') || str_contains($category, 'Command')) {
        return 'info';
    }
    if (str_contains($category, 'cache') || str_contains($category, 'Cache')) {
        return 'success';
    }
    if (str_contains($category, 'View') || str_contains($category, 'render') || str_contains($category, 'twig')) {
        return 'warning';
    }
    if (str_contains($category, 'mail') || str_contains($category, 'queue')) {
        return 'danger';
    }
    return 'muted';
};

$rulers = $dataProvider->getRulers();

// Hint shown only when the chart would be misleading: no data after filtering.
// When data exists we render the chart even on short requests so the dev sees
// the relative timings; the request-was-fast disclaimer would just be noise.
$showEmptyHint = !$hasData;
?>
<h1 class="yii-debug-sr-only">Timeline</h1>

<header class="yii-debug-grid-summary">
    <span><strong><?= number_format($totalDuration) ?></strong> ms total</span>
    <span class="yii-debug-grid-summary-sep">·</span>
    <span><strong><?= Html::encode($peakMemoryMB) ?></strong> peak memory</span>
    <span class="yii-debug-grid-summary-sep">·</span>
    <span><strong><?= count($models) ?></strong> spans</span>
</header>

<form class="yii-debug-tl-filter" method="get" action="<?= Url::to($panel->getUrl()) ?>">
    <input type="hidden" name="r" value="debug/default/view">
    <input type="hidden" name="panel" value="timeline">
    <input type="hidden" name="tag" value="<?= Html::encode($panel->tag ?? '') ?>">

    <div class="yii-debug-tl-field">
        <label for="tl-duration">Min duration (ms)</label>
        <input
            id="tl-duration"
            type="number"
            name="Search[duration]"
            min="0"
            step="0.1"
            placeholder="0"
            value="<?= Html::encode((string) ($searchModel->duration ?? '')) ?>"
        >
    </div>

    <div class="yii-debug-tl-field yii-debug-tl-field-grow">
        <label for="tl-category">Category</label>
        <input
            id="tl-category"
            type="text"
            name="Search[category]"
            placeholder="yii\db\Command::query"
            value="<?= Html::encode((string) ($searchModel->category ?? '')) ?>"
        >
    </div>

    <button type="submit" class="yii-debug-btn yii-debug-btn-primary yii-debug-btn-sm">Apply</button>
</form>

<?php if ($showEmptyHint): ?>
    <div class="yii-debug-tl-hint">
        <p class="yii-debug-tl-hint-title">
            <?php if (!$hasData): ?>
                No spans matched your filter.
            <?php else: ?>
                Request finished in <?= number_format($totalDuration) ?> ms — too short for a useful flame chart.
            <?php endif; ?>
        </p>
        <p class="yii-debug-tl-hint-body">
            The timeline is most useful for requests that take hundreds of milliseconds, where
            you can <em>see</em> which operations dominate. For quick requests the
            <?= Html::a('Profiling panel', [
                '/' . $panel->module->getUniqueId() . '/default/view',
                'panel' => 'profiling',
                'tag' => $panel->tag,
            ]) ?> presents the same data as a sortable list — easier to scan.
        </p>
    </div>
<?php endif; ?>

<?php if ($hasData): ?>
<section class="yii-debug-tl"<?= $showEmptyHint ? ' hidden' : '' ?>>
    <header class="yii-debug-tl-axis">
        <?php foreach ($rulers as $ms => $left): ?>
            <span class="yii-debug-tl-tick" style="left: <?= StringHelper::normalizeNumber($left) ?>%">
                <?= sprintf('%.1f ms', $ms) ?>
            </span>
        <?php endforeach; ?>
    </header>

    <div class="yii-debug-tl-rows" role="list">
        <?php foreach ($models as $key => $model): ?>
            <?php
            $variant = $categoryVariant((string) $model['category']);
            $depth = (int) ($model['child'] ?? 0);
            $left = StringHelper::normalizeNumber($model['css']['left']);
            $width = max((float) $model['css']['width'], 0.4);
            $widthStr = StringHelper::normalizeNumber((string) $width);
            $memoryDelta = '';
            if (!empty($model['memoryDiff'])) {
                $memoryDelta = sprintf(
                    '%s%.2f MB',
                    $model['memoryDiff'] > 0 ? '+' : '−',
                    abs($model['memoryDiff']) / 1048576,
                );
            }
            $tooltip = sprintf(
                "%s\n%.3f ms · %.2f MB%s",
                $model['info'] ?? $model['category'],
                $model['duration'],
                $model['memory'] / 1048576,
                $memoryDelta !== '' ? ' (' . $memoryDelta . ')' : '',
            );
            ?>
            <div
                class="yii-debug-tl-row yii-debug-tl-row-<?= $variant ?>"
                role="listitem"
                title="<?= Html::encode($tooltip) ?>"
            >
                <div class="yii-debug-tl-label" style="--depth: <?= $depth ?>">
                    <span class="yii-debug-tl-dot" aria-hidden="true"></span>
                    <span class="yii-debug-tl-name"><?= Html::encode((string) $model['category']) ?></span>
                </div>
                <div class="yii-debug-tl-track">
                    <div class="yii-debug-tl-bar" style="left: <?= $left ?>%; width: <?= $widthStr ?>%;">
                        <span class="yii-debug-tl-bar-duration"><?= sprintf('%.1f ms', $model['duration']) ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($panel->svg->hasPoints()): ?>
        <footer class="yii-debug-tl-memory">
            <span class="yii-debug-tl-memory-label">Memory</span>
            <div class="yii-debug-tl-memory-track" style="height: <?= StringHelper::normalizeNumber($panel->svg->y) ?>px;">
                <?= $panel->svg ?>
            </div>
            <span class="yii-debug-tl-memory-peak"><?= Html::encode($peakMemoryMB) ?></span>
        </footer>
    <?php endif; ?>
</section>
<?php endif; ?>
