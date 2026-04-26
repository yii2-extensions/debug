<?php

declare(strict_types=1);
/** @var yii\debug\panels\AssetPanel $panel */
$bundles = is_array($panel->data) ? $panel->data : [];
if ($bundles !== []):
    ?>
    <div class="yii-debug-toolbar-block">
        <a href="<?= $panel->getUrl() ?>" title="Number of asset bundles loaded">Asset Bundles <span
                class="yii-debug-toolbar-label yii-debug-toolbar-label-info"><?= count($bundles) ?></span></a>
    </div>
<?php endif; ?>
