<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var yii\debug\panels\RequestPanel $panel */

$summary = (array) (Yii::$app->controller->summary ?? []);
$statusCode = (int) ($panel->data['statusCode'] ?? $summary['statusCode'] ?? 0);
$statusVariant = match (true) {
    $statusCode >= 500 => 'danger',
    $statusCode >= 400 => 'warning',
    $statusCode >= 300 => 'muted',
    $statusCode >= 200 => 'success',
    default => 'muted',
};
$general = $panel->data['general'] ?? [];
$method = (string) ($general['method'] ?? $summary['method'] ?? '');
$url = (string) ($summary['url'] ?? '');
$ip = (string) ($summary['ip'] ?? '');
$time = !empty($summary['time']) ? date('H:i:s', (int) $summary['time']) : '';
$durationMs = isset($summary['processingTime']) ? sprintf('%.1f ms', (float) $summary['processingTime'] * 1000) : '';
$flags = [];
foreach (['isAjax' => 'AJAX', 'isPjax' => 'PJAX', 'isFlash' => 'Flash', 'isSecureConnection' => 'HTTPS'] as $key => $label) {
    if (!empty($general[$key])) {
        $flags[] = $label;
    }
}
?>
<h1 class="yii-debug-sr-only">Request</h1>
<header class="yii-debug-request-hero">
    <div class="yii-debug-request-hero-line">
        <?php if ($method !== ''): ?>
            <span class="yii-debug-request-hero-method"><?= Html::encode($method) ?></span>
        <?php endif; ?>
        <span class="yii-debug-request-hero-url" title="<?= Html::encode($url) ?>"><?= Html::encode($url) ?></span>
        <?php if ($statusCode > 0): ?>
            <span class="yii-debug-snapshot-status yii-debug-snapshot-status-<?= $statusVariant ?>"><?= $statusCode ?></span>
        <?php endif; ?>
    </div>
    <div class="yii-debug-request-hero-meta">
        <?php foreach (array_filter([$ip, $time, $durationMs]) as $piece): ?>
            <span><?= Html::encode($piece) ?></span>
        <?php endforeach; ?>
        <?php foreach ($flags as $flag): ?>
            <span class="yii-debug-snapshot-tag"><?= Html::encode($flag) ?></span>
        <?php endforeach; ?>
    </div>
</header>
<?php

$items = [
    'nav' => [],
    'content' => [],
];

$parametersContent = '';

$parametersContent .= $this->render('table', [
    'caption' => 'Routing',
    'values' => [
        'Route' => $panel->data['route'],
        'Action' => $panel->data['action'],
        'Parameters' => $panel->data['actionParams'],
    ],
]);

if (isset($panel->data['GET'])) {
    $parametersContent .= $this->render('table', ['caption' => 'Get', 'values' => $panel->data['GET']]);
}

if (isset($panel->data['POST'])) {
    $parametersContent .= $this->render('table', ['caption' => 'Post', 'values' => $panel->data['POST']]);
}

if (isset($panel->data['FILES'])) {
    $parametersContent .= $this->render('table', ['caption' => 'Files', 'values' => $panel->data['FILES']]);
}

if (isset($panel->data['COOKIE'])) {
    $parametersContent .= $this->render('table', ['caption' => 'Cookies', 'values' => $panel->data['COOKIE']]);
}

$parametersContent .= $this->render('table', ['caption' => 'Request Body', 'values' => $panel->data['requestBody']]);

$items['nav'][] = 'Parameters';
$items['content'][] = $parametersContent;

$items['nav'][] = 'Headers';
$items['content'][] = $this->render('table', ['caption' => 'Request Headers', 'values' => $panel->data['requestHeaders'], 'filterable' => true])
    . $this->render('table', ['caption' => 'Response Headers', 'values' => $panel->data['responseHeaders'], 'filterable' => true]);

if (isset($panel->data['SESSION'], $panel->data['flashes'])) {
    $items['nav'][] = 'Session';
    $items['content'][] = $this->render('table', ['caption' => 'Session', 'values' => $panel->data['SESSION'], 'filterable' => true])
        . $this->render('table', ['caption' => 'Flashes', 'values' => $panel->data['flashes']]);
}

if (isset($panel->data['SERVER'])) {
    $items['nav'][] = 'Server';
    $items['content'][] = $this->render('table', ['caption' => 'Server', 'values' => $panel->data['SERVER'], 'filterable' => true]);
}

?>
<ul class="yii-debug-tabs">
    <?php
    foreach ($items['nav'] as $k => $item) {
        echo Html::tag(
            'li',
            Html::a($item, '#r-tab-' . $k, [
                'class' => $k === 0 ? 'yii-debug-tab-link is-active' : 'yii-debug-tab-link',
                'data-yii-debug-toggle' => 'tab',
                'role' => 'tab',
                'aria-controls' => 'r-tab-' . $k,
                'aria-selected' => $k === 0 ? 'true' : 'false',
            ]),
            [
                'class' => 'yii-debug-tab',
            ],
        );
    }
?>
</ul>
<div class="yii-debug-tab-content">
    <?php
foreach ($items['content'] as $k => $item) {
    echo Html::tag('div', $item, [
        'class' => $k === 0 ? 'yii-debug-tab-panel is-active' : 'yii-debug-tab-panel',
        'id' => 'r-tab-' . $k,
    ]);
}
?>
</div>
