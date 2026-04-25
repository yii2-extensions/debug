<?php

declare(strict_types=1);

use yii\debug\widgets\NavigationButton;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var \yii\web\View $this */
/** @var array $summary */
/** @var string $tag */
/** @var array $manifest */
/** @var \yii\debug\Panel[] $panels */
/** @var \yii\debug\Panel $activePanel */

$this->title = 'Yii Debugger';

$historyItems = [];
$count = 0;
foreach ($manifest as $meta) {
    $label = ($meta['tag'] === $tag ? Html::tag('strong', '&#9658;&nbsp;' . $meta['tag']) : $meta['tag'])
        . ': ' . Html::encode($meta['method']) . ' ' . Html::encode($meta['url']) . ($meta['ajax'] ? ' (AJAX)' : '')
        . ', ' . date('Y-m-d h:i:s a', (int) $meta['time'])
        . ', ' . $meta['ip'];
    $historyItems[] = [
        'label' => $label,
        'url' => ['view', 'tag' => $meta['tag'], 'panel' => $activePanel->id],
    ];
    if (++$count >= 10) {
        break;
    }
}
?>
<div class="yii-debug-page default-view">
    <div id="yii-debug-toolbar" class="yii-debug-toolbar yii-debug-toolbar-position-top" style="display: none;">
        <div class="yii-debug-toolbar-bar">
            <div class="yii-debug-toolbar-block yii-debug-toolbar-title">
                <a href="<?= Url::to(['index']) ?>">
                    <img width="29" height="30" alt="" src="<?= \yii\debug\Module::getYiiLogo() ?>">
                </a>
            </div>

            <?php foreach ($panels as $panel): ?>
                <?= $panel->getSummary() ?>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="yii-debug-layout">
        <aside class="yii-debug-sidebar">
            <nav class="yii-debug-nav" aria-label="Debug panels">
                <?php foreach ($panels as $id => $panel): ?>
                    <?php
                    $isActive = $panel === $activePanel;
                    $linkOptions = ['class' => $isActive ? 'yii-debug-nav-link is-active' : 'yii-debug-nav-link'];
                    if ($isActive) {
                        $linkOptions['aria-current'] = 'page';
                    }
                    echo Html::a(Html::encode($panel->getName()), ['view', 'tag' => $tag, 'panel' => $id], $linkOptions);
                    ?>
                <?php endforeach; ?>
            </nav>
        </aside>

        <main class="yii-debug-main yii-debug-card">
            <?php if ($activePanel->hasRequestNavigation()): ?>
                <nav class="yii-debug-request-nav" aria-label="Request history">
                    <div class="yii-debug-btn-group" role="group">
                        <?= NavigationButton::widget(
                            ['manifest' => $manifest, 'tag' => $tag, 'panel' => $activePanel, 'button' => 'Prev'],
                        ) ?>
                        <?= NavigationButton::widget(
                            ['manifest' => $manifest, 'tag' => $tag, 'panel' => $activePanel, 'button' => 'Next'],
                        ) ?>
                    </div>
                    <div class="yii-debug-btn-group" role="group">
                        <?= Html::a('All', ['index'], ['class' => 'yii-debug-btn yii-debug-btn-ghost yii-debug-btn-sm']) ?>
                        <?= Html::a('Latest', ['view', 'panel' => $activePanel->id], ['class' => 'yii-debug-btn yii-debug-btn-ghost yii-debug-btn-sm']) ?>
                        <div class="yii-debug-dropdown">
                            <?= Html::button('Last 10 ▾', [
                                'type' => 'button',
                                'class' => 'yii-debug-btn yii-debug-btn-ghost yii-debug-btn-sm',
                                'data-yii-debug-toggle' => 'dropdown',
                                'aria-haspopup' => 'true',
                                'aria-expanded' => 'false',
                            ]) ?>
                            <?= \yii\widgets\Menu::widget([
                                'encodeLabels' => false,
                                'items' => $historyItems,
                                'options' => ['class' => 'yii-debug-dropdown-menu'],
                                'itemOptions' => ['tag' => 'li'],
                                'linkTemplate' => '<a href="{url}" class="yii-debug-dropdown-item">{label}</a>',
                            ]) ?>
                        </div>
                    </div>
                </nav>
            <?php endif; ?>
            <?= $activePanel->getDetail() ?>
        </main>
    </div>
</div>
<script type="text/javascript">
    if (window.top == window) {
        document.querySelector('#yii-debug-toolbar').style.display = 'block';
    }
</script>
