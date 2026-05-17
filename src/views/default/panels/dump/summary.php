<?php

declare(strict_types=1);
/** @var yii\debug\panels\AssetPanel $panel */
$dumps = is_array($panel->data) ? $panel->data : [];
if ($dumps !== []):
    ?>
    <div class="yii-debug-toolbar-block">
        <a href="<?= $panel->getUrl() ?>" title="Number of dumped variables">Dump
            <span class="yii-debug-toolbar-label yii-debug-toolbar-label-info"><?= count($dumps) ?></span>
        </a>
    </div>
<?php endif; ?>
