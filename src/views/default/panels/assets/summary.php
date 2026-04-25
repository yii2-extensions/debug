<?php

declare(strict_types=1);
/** @var yii\debug\panels\AssetPanel $panel */
if (!empty($panel->data)):
    ?>
    <div class="yii-debug-toolbar-block">
        <a href="<?= $panel->getUrl() ?>" title="Number of asset bundles loaded">Asset Bundles <span
                class="yii-debug-toolbar-label yii-debug-toolbar-label-info"><?= count($panel->data) ?></span></a>
    </div>
<?php endif; ?>
