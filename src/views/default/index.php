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
];

$totalRequests = count($manifest);

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
<script>
    // Delegate row-clicks: any click on a `.yii-debug-row-link` <tr> jumps to that request's
    // view. Clicks that started inside an interactive element (link, button, input) are left
    // alone so column-level links and the filter row keep working.
    (function () {
        document.addEventListener('click', function (event) {
            var row = event.target.closest('.yii-debug-row-link');
            if (!row) {
                return;
            }
            if (event.target.closest('a, button, input, select, textarea, label')) {
                return;
            }
            var href = row.getAttribute('data-href');
            if (!href) {
                return;
            }
            if (event.metaKey || event.ctrlKey || event.button === 1) {
                window.open(href, '_blank', 'noopener');
                return;
            }
            window.location.href = href;
        });
    }());

    // History cursor — peek at requests one by one without leaving the page. The sidebar's
    // snapshot card mirrors whichever GridView row the cursor sits on; Prev/Next/Latest move
    // the cursor and Last 10 dropdown items jump straight to a tag. Each row carries the
    // payload as `data-yii-debug-*` attrs so we don't need an extra JSON blob.
    (function () {
        var section = document.querySelector('[data-yii-debug-history-cursor]');
        if (!section) {
            return;
        }

        var rows = Array.prototype.slice.call(
            document.querySelectorAll('tr[data-yii-debug-tag]'),
        );
        if (rows.length === 0) {
            return;
        }

        var STATUS_VARIANTS = ['success', 'warning', 'danger', 'muted'];
        var cursor = 0;

        function snapshotFromRow(row) {
            var status = parseInt(row.getAttribute('data-yii-debug-status') || '0', 10);
            return {
                tag: row.getAttribute('data-yii-debug-tag') || '',
                method: row.getAttribute('data-yii-debug-method') || '',
                url: row.getAttribute('data-yii-debug-url') || '',
                status: status,
                // Pre-formatted on the server side (`H:i:s` in server timezone) so the snapshot
                // card matches what the GridView TIME column shows — JS-side reformatting would
                // drift on hosts where server and client clocks live in different zones.
                time: row.getAttribute('data-yii-debug-time') || '',
                ajax: row.getAttribute('data-yii-debug-ajax') === '1',
            };
        }

        function statusVariant(status) {
            if (status >= 500) return 'danger';
            if (status >= 400) return 'warning';
            if (status >= 300) return 'muted';
            if (status >= 200) return 'success';
            return 'muted';
        }

        function update() {
            // Highlight the cursor row, scroll it into view if it slipped off-screen.
            rows.forEach(function (r, i) {
                r.classList.toggle('is-cursor', i === cursor);
            });

            var snap = snapshotFromRow(rows[cursor]);

            section.querySelectorAll('[data-snapshot-field]').forEach(function (el) {
                var field = el.getAttribute('data-snapshot-field');
                if (field === 'method') {
                    el.textContent = snap.method;
                } else if (field === 'url') {
                    el.textContent = snap.url;
                } else if (field === 'status') {
                    el.textContent = snap.status ? String(snap.status) : '–';
                    STATUS_VARIANTS.forEach(function (v) {
                        el.classList.remove('yii-debug-snapshot-status-' + v);
                    });
                    el.classList.add('yii-debug-snapshot-status-' + statusVariant(snap.status));
                } else if (field === 'time') {
                    el.textContent = snap.time;
                    el.hidden = snap.time === '';
                } else if (field === 'ajax') {
                    el.hidden = !snap.ajax;
                }
            });

            var card = section.querySelector('.yii-debug-history-card');
            if (card) {
                card.setAttribute('title', (snap.method + ' ' + snap.url).trim());
            }

            // Button labels read positionally: First = top of the list (cursor 0, newest);
            // Latest = bottom of the list (cursor N-1, oldest). Prev/Next still step toward
            // newer/older neighbours (cursor -1 / +1).
            var firstBtn = section.querySelector('[data-yii-debug-cursor="first"]');
            var prevBtn = section.querySelector('[data-yii-debug-cursor="prev"]');
            var nextBtn = section.querySelector('[data-yii-debug-cursor="next"]');
            var latestBtn = section.querySelector('[data-yii-debug-cursor="latest"]');
            var atTop = cursor === 0;
            var atBottom = cursor === rows.length - 1;
            if (firstBtn) firstBtn.classList.toggle('is-disabled', atTop);
            if (prevBtn) prevBtn.classList.toggle('is-disabled', atTop);
            if (nextBtn) nextBtn.classList.toggle('is-disabled', atBottom);
            if (latestBtn) latestBtn.classList.toggle('is-disabled', atBottom);
        }

        function ensureVerticallyVisible(row) {
            // Avoid `scrollIntoView` — it also scrolls inline (horizontally), and the GridView is
            // usually wider than the viewport, so it would yank the page sideways. We only need
            // vertical visibility.
            var rect = row.getBoundingClientRect();
            var viewportHeight = window.innerHeight || document.documentElement.clientHeight;
            if (rect.top >= 0 && rect.bottom <= viewportHeight) {
                return;
            }
            var target = window.scrollY + rect.top - viewportHeight / 2 + rect.height / 2;
            window.scrollTo({ top: Math.max(0, target), behavior: 'smooth' });
        }

        function moveTo(index) {
            if (index < 0 || index >= rows.length || index === cursor) {
                return;
            }
            cursor = index;
            update();
            ensureVerticallyVisible(rows[cursor]);
        }

        section.addEventListener('click', function (event) {
            var btn = event.target.closest('[data-yii-debug-cursor]');
            if (btn && section.contains(btn)) {
                event.preventDefault();
                if (btn.classList.contains('is-disabled')) {
                    return;
                }
                var dir = btn.getAttribute('data-yii-debug-cursor');
                if (dir === 'prev') moveTo(cursor - 1);
                else if (dir === 'next') moveTo(cursor + 1);
                else if (dir === 'first') moveTo(0);
                else if (dir === 'latest') moveTo(rows.length - 1);
            }
        });

        update();
    }());
</script>
