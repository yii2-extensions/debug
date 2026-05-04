<?php

declare(strict_types=1);

use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;
use yii\debug\widgets\FilterBanner;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var \yii\web\View $this */
/** @var array $manifest */
/** @var \yii\debug\models\search\Debug $searchModel */
/** @var ArrayDataProvider $dataProvider */
/** @var \yii\debug\Panel[] $panels */
/** @var string $debugTheme Theme primed by DefaultController::primeThemeContext(). */
/** @var string $themeIconSun Pre-loaded sun glyph from the controller. */
/** @var string $themeIconMoon Pre-loaded moon glyph from the controller. */

$this->title = 'Yii Debugger';

// Layout-driven shell: the layout reads `shellMode` + `shellData` and renders
// the brand bar + sidebar around our content. We only emit the table area.
$this->params['shellMode'] = 'index';
$this->params['shellData'] = [
    'panels' => $panels,
    'manifest' => $manifest,
    'debugTheme' => $debugTheme,
    'themeIconSun' => $themeIconSun,
    'themeIconMoon' => $themeIconMoon,
    'cursorInit' => (string) (Yii::$app->getRequest()->get('cursor') ?? ''),
];

$totalRequests = count($manifest);

// `cursor` query param lets a panel-view's "History" link preserve the active
// tag — the inline JS reads `data-yii-debug-cursor-init` and lands the cursor
// on that row instead of snapping back to the latest capture.
$cursorInit = (string) (Yii::$app->getRequest()->get('cursor') ?? '');

$hasDbPanel = isset($panels['db']);
$hasMailPanel = isset($panels['mail']);

// Build the unique status-code filter from what's actually in the manifest so the dropdown only
// offers values the developer can hit.
$codes = [];
foreach ($manifest as $vals) {
    if (!empty($vals['statusCode'])) {
        $codes[] = $vals['statusCode'];
    }
}
$codes = array_unique($codes, SORT_NUMERIC);
$statusCodes = $codes !== [] ? array_combine($codes, $codes) : null;

// Aggregate the manifest into status-code buckets for the top summary strip. We pick a
// representative status per bucket so each pill can deep-link the GridView (Yii's filter
// is exact-match, not range).
$statusBuckets = ['2xx' => 0, '3xx' => 0, '4xx' => 0, '5xx' => 0];
$bucketSample = [];
foreach ($manifest as $row) {
    $code = (int) ($row['statusCode'] ?? 0);
    $bucket = match (true) {
        $code >= 500 && $code < 600 => '5xx',
        $code >= 400 && $code < 500 => '4xx',
        $code >= 300 && $code < 400 => '3xx',
        $code >= 200 && $code < 300 => '2xx',
        default => null,
    };
    if ($bucket === null) {
        continue;
    }
    $statusBuckets[$bucket]++;
    $bucketSample[$bucket] ??= $code;
}
$bucketVariant = [
    '2xx' => 'success',
    '3xx' => 'info',
    '4xx' => 'warn',
    '5xx' => 'danger',
];
?>
<?php if ($totalRequests > 0): ?>
    <header class="yii-debug-grid-summary">
        <span><strong><?= $totalRequests ?></strong> captured request<?= $totalRequests === 1 ? '' : 's' ?></span>
        <?php foreach ($statusBuckets as $bucket => $count): ?>
            <?php if ($count === 0) {
                continue;
            } ?>
            <span class="yii-debug-grid-summary-sep">·</span>
            <a class="yii-debug-grid-summary-stat-<?= $bucketVariant[$bucket] ?>"
               href="<?= Html::encode(Url::to(['index', 'Debug[statusCode]' => $bucketSample[$bucket]])) ?>"
               title="Filter to <?= Html::encode($bucket) ?> responses (sample <?= Html::encode((string) $bucketSample[$bucket]) ?>)">
                <strong><?= (int) $count ?></strong> <?= Html::encode($bucket) ?>
            </a>
        <?php endforeach; ?>
        <?= GridViewConfig::pageSizeSelectorHtml() ?>
    </header>
<?php endif; ?>

<?= FilterBanner::widget(['searchModel' => $searchModel]) ?>

<?php if ($totalRequests === 0): ?>
    <div class="yii-debug-empty-state">
        <h2>No debug data captured yet</h2>
        <p>Browse the host application to populate this view. Each request is captured automatically while <code>YII_DEBUG</code> is enabled.</p>
    </div>
<?php else: ?>
    <?= GridView::widget(array_merge(GridViewConfig::defaults(), [
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'rowOptions' => static function ($model) use ($searchModel): array {
            $base = $searchModel->isCodeCritical($model['statusCode'])
                ? GridViewConfig::rowClassFor('danger')
                : [];
            // Make every row a one-click jump to that request's view; the JS handler
            // ignores clicks that originate inside an <a>, <input>, <button> etc.
            $base['class'] = trim(($base['class'] ?? '') . ' yii-debug-row-link');
            $base['data-href'] = Url::to(['view', 'tag' => $model['tag']]);
            // Snapshot payload consumed by the sidebar's history-cursor JS — Prev/Next/Latest/Last10
            // restamp the snapshot card from these data attrs without leaving the page.
            $base['data-yii-debug-tag'] = (string) ($model['tag'] ?? '');
            $base['data-yii-debug-method'] = (string) ($model['method'] ?? '');
            $base['data-yii-debug-url'] = (string) ($model['url'] ?? '');
            $base['data-yii-debug-status'] = (string) (int) ($model['statusCode'] ?? 0);
            $base['data-yii-debug-time'] = !empty($model['time'])
                ? date('H:i:s', (int) $model['time'])
                : '';
            $base['data-yii-debug-ajax'] = !empty($model['ajax']) ? '1' : '';
            return $base;
        },
        'columns' => array_filter([
            [
                'class' => 'yii\grid\SerialColumn',
                'headerOptions' => ['class' => 'yii-debug-col-num'],
                'contentOptions' => ['class' => 'yii-debug-col-num'],
                'filterOptions' => ['class' => 'yii-debug-col-num'],
            ],
            [
                'attribute' => 'tag',
                'label' => 'ID',
                'value' => static function ($data): string {
                    return Html::a(Html::encode((string) $data['tag']), ['view', 'tag' => $data['tag']], [
                        'class' => 'yii-debug-tag-link',
                    ]);
                },
                'format' => 'raw',
                'headerOptions' => ['class' => 'yii-debug-col-id'],
                'contentOptions' => ['class' => 'yii-debug-col-id'],
            ],
            [
                'attribute' => 'time',
                'value' => static function ($data): string {
                    $full = Yii::$app->formatter->asDatetime($data['time'], 'yyyy-MM-dd HH:mm:ss');
                    $compact = Yii::$app->formatter->asTime($data['time'], 'HH:mm:ss');
                    return '<span class="yii-debug-nowrap" title="' . Html::encode($full) . '">'
                        . Html::encode($compact)
                        . '</span>';
                },
                'format' => 'raw',
            ],
            [
                'attribute' => 'processingTime',
                'label' => 'Duration',
                'value' => static function ($data): string {
                    return isset($data['processingTime'])
                        ? number_format($data['processingTime'] * 1000) . ' ms'
                        : '<span class="yii-debug-not-set">(not set)</span>';
                },
                'format' => 'raw',
            ],
            [
                'attribute' => 'peakMemory',
                'label' => 'Memory',
                'value' => static function ($data): string {
                    return isset($data['peakMemory'])
                        ? sprintf('%.3f MB', $data['peakMemory'] / 1048576)
                        : '<span class="yii-debug-not-set">(not set)</span>';
                },
                'format' => 'raw',
            ],
            'ip',
            $hasDbPanel ? [
                'attribute' => 'sqlCount',
                'label' => 'Query',
                'headerOptions' => ['class' => 'yii-debug-col-num'],
                'contentOptions' => ['class' => 'yii-debug-col-num'],
                'filterOptions' => ['class' => 'yii-debug-col-num'],
                'value' => function ($data): string {
                    /** @var \yii\debug\panels\DbPanel $dbPanel */
                    $dbPanel = $this->context->module->panels['db'];

                    $title = "Executed {$data['sqlCount']} database queries.";
                    $warning = '';
                    if ($dbPanel->isQueryCountCritical($data['sqlCount'])) {
                        $warning .= 'Too many queries. Allowed count is ' . $dbPanel->criticalQueryThreshold;
                    }
                    if (!empty($data['excessiveCallersCount'])) {
                        $warning .= ($warning ? ' &#10;' : '') . $data['excessiveCallersCount'] . ' '
                            . ($data['excessiveCallersCount'] === 1 ? 'caller is' : 'callers are')
                            . ' making too many calls.';
                    }

                    $content = (string) $data['sqlCount'];
                    if ($warning !== '') {
                        $content .= ' <span title="' . Html::encode($warning) . '">&#x26a0;</span>';
                    }

                    return '<a href="' . Html::encode(Url::to(['view', 'panel' => 'db', 'tag' => $data['tag']])) . '" title="' . Html::encode($title) . '">' . $content . '</a>';
                },
                'format' => 'raw',
            ] : null,
            $hasMailPanel ? [
                'attribute' => 'mailCount',
                'label' => 'Mail',
                'headerOptions' => ['class' => 'yii-debug-col-num'],
                'contentOptions' => ['class' => 'yii-debug-col-num'],
                'filterOptions' => ['class' => 'yii-debug-col-num'],
            ] : null,
            [
                'attribute' => 'method',
                'filter' => [
                    'get' => 'GET',
                    'post' => 'POST',
                    'delete' => 'DELETE',
                    'put' => 'PUT',
                    'head' => 'HEAD',
                    'command' => 'COMMAND',
                ],
            ],
            [
                'attribute' => 'ajax',
                'value' => static function ($data): string {
                    return $data['ajax'] ? 'Yes' : 'No';
                },
                'filter' => ['No', 'Yes'],
            ],
            [
                'attribute' => 'url',
                'label' => 'URL',
                'value' => static function ($data): string {
                    $url = (string) ($data['url'] ?? '');
                    return '<span class="yii-debug-url-cell" title="' . Html::encode($url) . '">'
                        . Html::encode($url) . '</span>';
                },
                'format' => 'raw',
            ],
            [
                'attribute' => 'statusCode',
                'value' => static function ($data): string {
                    $statusCode = $data['statusCode'];
                    $method = $data['method'];
                    if ($statusCode === null) {
                        $statusCode = 200;
                    }
                    if (($statusCode >= 200 && $statusCode < 300) || ($method === 'COMMAND' && $statusCode === 0)) {
                        $variant = 'success';
                    } elseif ($statusCode >= 300 && $statusCode < 400) {
                        $variant = 'info';
                    } else {
                        $variant = 'danger';
                    }
                    return "<span class=\"yii-debug-badge yii-debug-badge--{$variant}\">{$statusCode}</span>";
                },
                'format' => 'raw',
                'filter' => $statusCodes,
                'label' => 'Status',
            ],
        ]),
    ])) ?>
<?php endif; ?>
