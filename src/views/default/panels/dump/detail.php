<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Phrasing\Span;
use UIAwesome\Html\Root\Header;
use yii\debug\GridViewConfig;
use yii\debug\widgets\FilterBanner;
use yii\grid\GridView;
use yii\helpers\Html;

/** @var yii\debug\panels\DumpPanel $panel */
/** @var yii\debug\models\search\Log $searchModel */
/** @var yii\data\ArrayDataProvider $dataProvider */
?>
    <h1 class="yii-debug-sr-only">Dump</h1>
    <header class="yii-debug-grid-summary">
        <span><strong><?= $dataProvider->getTotalCount() ?></strong> dumps captured</span>
        <?= GridViewConfig::pageSizeSelectorHtml() ?>
    </header>
    <?= FilterBanner::widget(['searchModel' => $searchModel]) ?>
<?php

echo GridView::widget(array_merge(GridViewConfig::defaults(), [
    'dataProvider' => $dataProvider,
    'id' => 'dump-panel-detailed-grid',
    'filterModel' => $searchModel,
    'columns' => [
        'category',
        [
            'attribute' => 'message',
            'value' => static function (array $data, $key, int $index) use ($panel): string {
                $message = is_string($data['message'] ?? null) ? $data['message'] : '';
                $time = is_numeric($data['time'] ?? null) ? (float) $data['time'] : 0.0;

                $trace = $data['trace'] ?? null;
                $firstFrame = is_array($trace) && isset($trace[0]) && is_array($trace[0]) ? $trace[0] : null;
                $file = $firstFrame !== null && is_string($firstFrame['file'] ?? null) ? $firstFrame['file'] : '';
                $line = $firstFrame !== null && is_int($firstFrame['line'] ?? null) ? $firstFrame['line'] : null;

                // Type sniff from PHP's `highlight_string()` output. Decode HTML
                // entities so `&lt;?php …` reads as plain text and the first
                // payload character (`[`, `'`, digit, identifier) classifies the
                // dumped value. A miss just hides the badge — never blocks render.
                $typeKey = '';
                $typeLabel = '';
                $plain = html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $payload = ltrim((string) preg_replace('/^\s*<\?php\s*/', '', $plain));

                if ($payload !== '') {
                    $first = $payload[0];
                    if ($first === '[') {
                        $typeKey = 'array';
                        $typeLabel = 'array';
                    } elseif ($first === "'" || $first === '"') {
                        $typeKey = 'string';
                        $typeLabel = 'string';
                    } elseif (preg_match('/^([A-Za-z_][A-Za-z0-9_\\\\]*)/', $payload, $m)) {
                        $name = $m[1];
                        if (in_array(strtolower($name), ['true', 'false'], true)) {
                            $typeKey = 'bool';
                            $typeLabel = 'bool';
                        } elseif (strtolower($name) === 'null') {
                            $typeKey = 'null';
                            $typeLabel = 'null';
                        } else {
                            $typeKey = 'object';
                            $typeLabel = $name;
                        }
                    } elseif (preg_match('/^-?\d/', $payload)) {
                        $typeKey = 'number';
                        $typeLabel = 'number';
                    }
                }

                $timeStr = $time > 0
                    ? date('H:i:s', (int) $time) . '.' . sprintf('%03d', (int) (($time - floor($time)) * 1000))
                    : '';

                $headChildren = [
                    Span::tag()
                        ->class('yii-debug-dump-index')
                        ->addAriaAttribute('hidden', 'true')
                        ->content('#' . ($index + 1)),
                ];

                if ($typeLabel !== '') {
                    $headChildren[] = Span::tag()
                        ->class('yii-debug-dump-type')
                        ->addDataAttribute('type', $typeKey)
                        ->content($typeLabel);
                }

                $metaChildren = [];

                if ($timeStr !== '') {
                    $metaChildren[] = Span::tag()
                        ->class('yii-debug-dump-time')
                        ->content($timeStr);
                }

                if ($file !== '') {
                    $traceLabel = basename($file) . ($line !== null && $line > 0 ? ':' . $line : '');
                    $metaChildren[] = Span::tag()
                        ->class('yii-debug-dump-trace')
                        ->title($file . ($line !== null && $line > 0 ? ':' . $line : ''))
                        ->content($traceLabel);
                }

                $headChildren[] = Span::tag()
                    ->class('yii-debug-dump-meta')
                    ->html(...$metaChildren);

                $head = Header::tag()
                    ->class('yii-debug-dump-card-head')
                    ->html(...$headChildren);

                $traceList = '';

                if (is_array($trace) && $trace !== []) {
                    $traceList = Html::ul($trace, [
                        'class' => 'yii-debug-trace',
                        'item' => static function ($traceItem) use ($panel): string {
                            return '<li>' . $panel->getTraceLine(is_array($traceItem) ? $traceItem : []) . '</li>';
                        },
                    ]);
                }

                return Div::tag()
                    ->class('yii-debug-dump')
                    ->html(
                        $head,
                        Div::tag()
                            ->class('yii-debug-dump-body')
                            ->html($message . $traceList),
                    )
                    ->render();
            },
            'format' => 'raw',
            'options' => [
                'width' => '80%',
            ],
        ],
    ],
]));
