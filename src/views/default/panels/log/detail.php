<?php

declare(strict_types=1);

/** @var yii\debug\panels\LogPanel $panel */
/** @var yii\debug\models\search\Log $searchModel */
/** @var yii\data\ArrayDataProvider $dataProvider */

use yii\debug\GridViewConfig;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\VarDumper;
use yii\log\Logger;

$levelToVariant = [
    Logger::LEVEL_ERROR => 'danger',
    Logger::LEVEL_WARNING => 'warning',
    Logger::LEVEL_INFO => 'info',
];

$counts = ['total' => 0, 'errors' => 0, 'warnings' => 0, 'info' => 0];
foreach ((array) ($panel->data['messages'] ?? []) as $entry) {
    $counts['total']++;
    $level = (int) ($entry[1] ?? 0);
    match ($level) {
        Logger::LEVEL_ERROR => $counts['errors']++,
        Logger::LEVEL_WARNING => $counts['warnings']++,
        Logger::LEVEL_INFO => $counts['info']++,
        default => null,
    };
}

?>
    <h1 class="yii-debug-sr-only">Log Messages</h1>
    <header class="yii-debug-grid-summary">
        <span><strong><?= $counts['total'] ?></strong> messages</span>
        <?php if ($counts['errors'] > 0): ?>
            <span class="yii-debug-grid-summary-sep">·</span>
            <span class="yii-debug-grid-summary-stat-danger"><strong><?= $counts['errors'] ?></strong> errors</span>
        <?php endif; ?>
        <?php if ($counts['warnings'] > 0): ?>
            <span class="yii-debug-grid-summary-sep">·</span>
            <span class="yii-debug-grid-summary-stat-warn"><strong><?= $counts['warnings'] ?></strong> warnings</span>
        <?php endif; ?>
        <?php if ($counts['info'] > 0): ?>
            <span class="yii-debug-grid-summary-sep">·</span>
            <span><strong><?= $counts['info'] ?></strong> info</span>
        <?php endif; ?>
    </header>
<?php
echo GridView::widget(array_merge(GridViewConfig::defaults(), [
    'dataProvider' => $dataProvider,
    'id' => 'log-panel-detailed-grid',
    'options' => ['class' => 'yii-debug-grid yii-debug-grid-log'],
    'filterModel' => $searchModel,
    'filterUrl' => $panel->getUrl(),
    'rowOptions' => static function ($model) use ($levelToVariant) {
        $options = ['id' => 'log-' . $model['id']];
        $variant = $levelToVariant[$model['level']] ?? null;
        if ($variant !== null) {
            Html::addCssClass($options, 'yii-debug-row--' . $variant);
        }
        return $options;
    },
    'columns' => [
        [
            'attribute' => 'id',
            'label' => '#',
            'contentOptions' => ['class' => 'yii-debug-nowrap'],
        ],
        [
            'attribute' => 'time',
            'value' => static function ($data) {
                $timeInSeconds = $data['time'] / 1000;
                $millisecondsDiff = (int) (($timeInSeconds - (int) $timeInSeconds) * 1000);

                return date('H:i:s.', (int) $timeInSeconds) . sprintf('%03d', $millisecondsDiff);
            },
            'headerOptions' => ['class' => 'sort-numerical'],
            'contentOptions' => ['class' => 'yii-debug-nowrap'],
        ],
        [
            'attribute' => 'time_since_previous',
            'value' => static function ($data) {
                $diffInMs = $data['time'] - $data['time_of_previous'];
                $diffInSeconds = $diffInMs / 1000;
                $diffInMinutes = $diffInSeconds / 60;
                $diffInHours = $diffInMinutes / 60;

                $diffMs = (int) $diffInMs % 1000;
                $diffSeconds = (int) $diffInSeconds % 60;
                $diffMinutes = (int) $diffInMinutes % 60;
                $diffHours = (int) $diffInHours;

                $formattedDiff = [];
                if ($diffHours > 0) {
                    $formattedDiff[] = $diffHours . 'h';
                }
                if ($diffMinutes > 0) {
                    $formattedDiff[] = $diffMinutes . 'm';
                }
                if ($diffSeconds > 0) {
                    $formattedDiff[] = $diffSeconds . 's';
                }
                $formattedDiff[] = $diffMs . 'ms';
                $formattedDiff = implode('&nbsp;', $formattedDiff);

                $btnClass = 'yii-debug-since-previous-btn';

                if ($data['id_of_previous'] === null) {
                    $previous = Html::tag('span', '<', ['class' => $btnClass . ' is-disabled']);
                } else {
                    $previous = Html::a('<', '#log-' . $data['id_of_previous'], ['class' => $btnClass]);
                }

                if ($data['id_of_next'] === null) {
                    $next = Html::tag('span', '>', ['class' => $btnClass . ' is-disabled']);
                } else {
                    $next = Html::a('>', '#log-' . $data['id_of_next'], ['class' => $btnClass]);
                }

                return '<div class="yii-debug-since-previous">' . $previous . '<span>' . $formattedDiff . '</span>' . $next . '</div>';
            },
            'format' => 'raw',
            'headerOptions' => ['class' => 'sort-numerical'],
        ],
        [
            'attribute' => 'level',
            'value' => static function ($data) {
                return Logger::getLevelName($data['level']);
            },
            'filter' => [
                Logger::LEVEL_TRACE => ' Trace ',
                Logger::LEVEL_INFO => ' Info ',
                Logger::LEVEL_WARNING => ' Warning ',
                Logger::LEVEL_ERROR => ' Error ',
            ],
        ],
        'category',
        [
            'attribute' => 'message',
            'value' => static function ($data) use ($panel) {
                $message = Html::encode(is_string($data['message']) ? $data['message'] : VarDumper::export($data['message']));
                if (!empty($data['trace'])) {
                    $message .= Html::ul($data['trace'], [
                        'class' => 'yii-debug-trace',
                        'item' => static function ($trace) use ($panel) {
                            return '<li>' . $panel->getTraceLine($trace) . '</li>';
                        },
                    ]);
                }
                return $message;
            },
            'format' => 'raw',
            'options' => ['width' => '50%'],
        ],
    ],
]));
