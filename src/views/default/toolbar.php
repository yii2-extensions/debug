<?php

declare(strict_types=1);

use UIAwesome\Html\Helper\Encode;
use yii\helpers\Url;

/** @var \yii\web\View $this */
/** @var array<string, \yii\debug\Panel> $panels */
/** @var string $tag */
/** @var string $position */
/** @var int $defaultHeight */
?>
<div id="yii-debug-toolbar" class="yii-debug-toolbar yii-debug-toolbar-position-<?= $position ?>" data-height="<?= $defaultHeight ?>">
    <div class="yii-debug-toolbar-resize-handle"></div>
    <div class="yii-debug-toolbar-bar">
        <div class="yii-debug-toolbar-block yii-debug-toolbar-title">
            <a href="<?= Url::to(['index']) ?>">
                <img width="30" height="30" alt="Yii" src="<?= \yii\debug\Module::getYiiLogo() ?>">
            </a>
        </div>

        <div class="yii-debug-toolbar-block yii-debug-toolbar-ajax" style="display: none">
            AJAX <span class="yii-debug-toolbar-label yii-debug-toolbar-ajax-counter">0</span>
            <div class="yii-debug-toolbar-ajax-info">
                <table>
                    <thead>
                    <tr>
                        <th>Method</th>
                        <th>Status</th>
                        <th>URL</th>
                        <th>Time</th>
                        <th>Profile</th>
                    </tr>
                    </thead>
                    <tbody class="yii-debug-toolbar-ajax-requests"></tbody>
                </table>
            </div>
        </div>

        <?php foreach ($panels as $panel): ?>
            <?php if ($panel->hasError()): ?>
                <?php $panelError = $panel->getError(); ?>
                <div class="yii-debug-toolbar-block">
                    <a href="<?= $panel->getUrl() ?>"
                        title="<?= Encode::value($panelError !== null ? $panelError->getMessage() : '') ?>"><?= Encode::content($panel->getName()) ?>
                        <span class="yii-debug-toolbar-label yii-debug-toolbar-label-error">error</span></a>
                </div>
            <?php else: ?>
                <?= $panel->getSummary() ?>
            <?php endif; ?>
        <?php endforeach; ?>

        <div class="yii-debug-toolbar-block-last">

        </div>
        <a class="yii-debug-toolbar-external" href="#" target="_blank">
            <span class="yii-debug-toolbar-external-icon"></span>
        </a>

        <span class="yii-debug-toolbar-toggle">
            <span class="yii-debug-toolbar-toggle-icon"></span>
        </span>
    </div>

    <div class="yii-debug-toolbar-view">
        <iframe src="about:blank" frameborder="0" title="Yii2 debug bar"></iframe>
    </div>
</div>
