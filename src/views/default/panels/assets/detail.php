<?php

declare(strict_types=1);

use yii\debug\helpers\Icon;
use yii\debug\panels\asset\{AssetCardRenderer, AssetSummary};

/**
 * @var AssetSummary $summary
 */
?>
<h1 class="yii-debug-sr-only">Asset Bundles</h1>

<?php if ($summary->isEmpty()): ?>
    <div class="yii-debug-empty-state">
        <h2>No asset bundles loaded</h2>
        <p>This request did not register any <code>yii\web\AssetBundle</code> via <code>register()</code>, so the inventory is empty.</p>
        <p>Bundles appear here when something in the request actively pulls them in — typically a layout or view that calls a bundle's <code>register()</code>, or any bundle reached transitively through the <code>depends</code> chain.</p>
    </div>
<?php else: ?>
    <header class="yii-debug-asset-stats">
        <div class="yii-debug-asset-stat" data-kind="bundles">
            <span class="yii-debug-asset-stat-icon" aria-hidden="true"><?= Icon::render('asset') ?></span>
            <strong class="yii-debug-asset-stat-value"><?= $summary->totalBundles ?></strong>
            <span class="yii-debug-asset-stat-label">bundle<?= $summary->totalBundles === 1 ? '' : 's' ?></span>
        </div>
        <div class="yii-debug-asset-stat" data-kind="css">
            <span class="yii-debug-asset-stat-icon" aria-hidden="true"><?= Icon::render('brand-css3') ?></span>
            <strong class="yii-debug-asset-stat-value"><?= $summary->totalCss ?></strong>
            <span class="yii-debug-asset-stat-label">css</span>
        </div>
        <div class="yii-debug-asset-stat" data-kind="js">
            <span class="yii-debug-asset-stat-icon" aria-hidden="true"><?= Icon::render('brand-javascript') ?></span>
            <strong class="yii-debug-asset-stat-value"><?= $summary->totalJs ?></strong>
            <span class="yii-debug-asset-stat-label">js</span>
        </div>
        <div class="yii-debug-asset-stat" data-kind="deps">
            <span class="yii-debug-asset-stat-icon" aria-hidden="true"><?= Icon::render('link') ?></span>
            <strong class="yii-debug-asset-stat-value"><?= $summary->totalDeps ?></strong>
            <span class="yii-debug-asset-stat-label">link<?= $summary->totalDeps === 1 ? '' : 's' ?></span>
        </div>
    </header>

    <ol class="yii-debug-asset-list">
        <?php foreach ($summary->bundles as $bundle): ?>
            <li class="yii-debug-asset-list-item"><?= AssetCardRenderer::renderCard($bundle, $summary) ?></li>
        <?php endforeach; ?>
    </ol>
<?php endif;
