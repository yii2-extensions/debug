<?php

declare(strict_types=1);

use PHPForge\Debug\Storage\RequestSummary;
use yii\debug\Module;
use yii\helpers\{Html, Url};
use yii\web\View;

/**
 * @var string|null $baseline Selected baseline tag.
 * @var array<string, RequestSummary> $manifest Debug manifest, newest first.
 * @var string|null $target Selected target tag.
 * @var View $this View component instance.
 */
$options = [];

foreach ($manifest as $tag => $summary) {
    $time = $summary->time > 0 ? date('H:i:s', (int) $summary->time) : 'time unavailable';
    $url = mb_strimwidth($summary->url, 0, 72, '...');
    $shortTag = substr($tag, 0, 8);
    $method = $summary->method !== '' ? $summary->method : 'UNKNOWN';

    $options[$tag] = "{$time} · {$method} · {$url} · {$shortTag}";
}
?>
<?= Html::beginForm(Url::to(Module::route('compare')), 'get', ['class' => 'yii-debug-compare-form']) ?>
    <div class="yii-debug-field">
        <?= Html::label('Baseline capture', 'yii-debug-compare-baseline', ['class' => 'yii-debug-label']) ?>
        <?= Html::dropDownList(
            'baseline',
            $baseline,
            $options,
            [
                'class' => 'yii-debug-select',
                'id' => 'yii-debug-compare-baseline',
                'required' => true,
            ],
        ) ?>
    </div>
    <div class="yii-debug-field">
        <?= Html::label('Target capture', 'yii-debug-compare-target', ['class' => 'yii-debug-label']) ?>
        <?= Html::dropDownList(
            'target',
            $target,
            $options,
            [
                'class' => 'yii-debug-select',
                'id' => 'yii-debug-compare-target',
                'required' => true,
            ],
        ) ?>
    </div>
    <div class="yii-debug-field">
        <?= Html::submitButton('Compare captures', ['class' => 'yii-debug-btn yii-debug-btn-primary']) ?>
    </div>
<?= Html::endForm() ?>
