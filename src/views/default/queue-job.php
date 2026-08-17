<?php

declare(strict_types=1);

use yii\debug\Module;
use UIAwesome\Html\Palpable\A;
use PHPForge\Debug\Panel\Queue\{JobRecord, QueueCardRenderer};
use yii\helpers\Url;
use yii\web\View;

/**
 * @var JobRecord $record Queue job record being displayed.
 * @var string $tag Active request tag.
 * @var View $this View component instance.
 */
$this->title = 'Yii Debugger — Queue job';

$backUrl = Url::to(Module::route('view', ['tag' => $tag, 'panel' => 'queue']));
?>
<div class="yii-debug-queue-job-page">
    <header class="yii-debug-queue-job-head">
        <?= A::tag()
            ->class('yii-debug-btn yii-debug-btn-ghost')
            ->content('← Back to grid')
            ->href($backUrl) ?>
    </header>

    <?= QueueCardRenderer::renderItem($record) ?>
</div>
