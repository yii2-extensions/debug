<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Inflector;

/** @var yii\debug\panels\AssetPanel $panel */

// Helper to inline SVG glyphs from src/assets/svg/. Read once per file via the cache, so the
// 4 icons rendered in the stats strip + N bundle headers don't trigger N+4 file_get_contents.
// __DIR__ here is src/views/default/panels/assets, so we walk up 4 levels to reach src/.
$svgRoot = dirname(__DIR__, 4) . '/assets/svg/';
$svgCache = [];
$inlineSvg = static function (string $name) use ($svgRoot, &$svgCache): string {
    if (!isset($svgCache[$name])) {
        $path = $svgRoot . $name;
        $svgCache[$name] = is_file($path) ? trim((string) file_get_contents($path)) : '';
    }
    return $svgCache[$name];
};

$cubeIcon = $inlineSvg('asset.svg');
$cssIcon = $inlineSvg('brand-css3.svg');
$boltIcon = $inlineSvg('bolt.svg');
$linkIcon = $inlineSvg('link.svg');

$bundles = is_array($panel->data) ? $panel->data : [];
$totalBundles = count($bundles);

$totalCss = 0;
$totalJs = 0;
$totalDeps = 0;

foreach ($bundles as $bundle) {
    if (!is_array($bundle)) {
        continue;
    }
    $totalCss += is_array($bundle['css'] ?? null) ? count($bundle['css']) : 0;
    $totalJs += is_array($bundle['js'] ?? null) ? count($bundle['js']) : 0;
    $totalDeps += is_array($bundle['depends'] ?? null) ? count($bundle['depends']) : 0;
}

$shortName = static function (string $fqcn): string {
    $pos = strrpos($fqcn, '\\');
    return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
};

$namespacePart = static function (string $fqcn): string {
    $pos = strrpos($fqcn, '\\');
    return $pos === false ? '' : substr($fqcn, 0, $pos);
};

$fileLabel = static function (mixed $item): string {
    if (is_array($item)) {
        $item = reset($item);
    }
    return is_string($item) ? $item : '';
};
?>
<h1 class="yii-debug-sr-only">Asset Bundles</h1>

<?php if ($bundles === []): ?>
    <div class="yii-debug-empty-state">
        <h2>No asset bundles loaded</h2>
        <p>This request did not register any <code>yii\web\AssetBundle</code> via <code>register()</code>, so the inventory is empty.</p>
        <p>Bundles appear here when something in the request actively pulls them in — typically a layout or view that calls a bundle's <code>register()</code>, or any bundle reached transitively through the <code>depends</code> chain.</p>
    </div>
<?php else: ?>
    <header class="yii-debug-asset-stats">
        <div class="yii-debug-asset-stat" data-kind="bundles">
            <span class="yii-debug-asset-stat-icon" aria-hidden="true"><?= $cubeIcon ?></span>
            <strong class="yii-debug-asset-stat-value"><?= $totalBundles ?></strong>
            <span class="yii-debug-asset-stat-label">bundle<?= $totalBundles === 1 ? '' : 's' ?></span>
        </div>
        <div class="yii-debug-asset-stat" data-kind="css">
            <span class="yii-debug-asset-stat-icon" aria-hidden="true"><?= $cssIcon ?></span>
            <strong class="yii-debug-asset-stat-value"><?= $totalCss ?></strong>
            <span class="yii-debug-asset-stat-label">css</span>
        </div>
        <div class="yii-debug-asset-stat" data-kind="js">
            <span class="yii-debug-asset-stat-icon" aria-hidden="true"><?= $boltIcon ?></span>
            <strong class="yii-debug-asset-stat-value"><?= $totalJs ?></strong>
            <span class="yii-debug-asset-stat-label">js</span>
        </div>
        <div class="yii-debug-asset-stat" data-kind="deps">
            <span class="yii-debug-asset-stat-icon" aria-hidden="true"><?= $linkIcon ?></span>
            <strong class="yii-debug-asset-stat-value"><?= $totalDeps ?></strong>
            <span class="yii-debug-asset-stat-label">link<?= $totalDeps === 1 ? '' : 's' ?></span>
        </div>
    </header>

    <ol class="yii-debug-asset-list">
        <?php foreach ($bundles as $name => $bundle): ?>
            <?php
            $name = (string) $name;
            $bundle = is_array($bundle) ? $bundle : [];
            $id = Inflector::camel2id($name);
            $css = is_array($bundle['css'] ?? null) ? $bundle['css'] : [];
            $js = is_array($bundle['js'] ?? null) ? $bundle['js'] : [];
            $depends = is_array($bundle['depends'] ?? null) ? $bundle['depends'] : [];
            $sourcePath = is_string($bundle['sourcePath'] ?? null) ? $bundle['sourcePath'] : '';
            $basePath = is_string($bundle['basePath'] ?? null) ? $bundle['basePath'] : '';
            $baseUrl = is_string($bundle['baseUrl'] ?? null) ? $bundle['baseUrl'] : '';
            $cssCount = count($css);
            $jsCount = count($js);
            $depsCount = count($depends);
            $hasFiles = $cssCount + $jsCount > 0;
            $hasWiring = $sourcePath !== '' || $basePath !== '' || $baseUrl !== '';
            $hasDepends = $depsCount > 0;
            $shortBundleName = $shortName($name);
            $bundleNamespace = $namespacePart($name);
            $bodyCols = ($hasFiles && ($hasWiring || $hasDepends)) ? '2' : '1';
            ?>
            <li class="yii-debug-asset-list-item">
                <article class="yii-debug-asset-card" id="<?= Html::encode($id) ?>">
                    <header class="yii-debug-asset-card-head">
                        <span class="yii-debug-asset-card-icon" aria-hidden="true"><?= $cubeIcon ?></span>
                        <div class="yii-debug-asset-card-title">
                            <h2 class="yii-debug-asset-card-name"><?= Html::encode($shortBundleName) ?></h2>
                            <?php if ($bundleNamespace !== ''): ?>
                                <p class="yii-debug-asset-card-fqcn"><?= Html::encode($bundleNamespace) ?>\</p>
                            <?php endif; ?>
                        </div>
                        <div class="yii-debug-asset-card-meta">
                            <?php if ($cssCount > 0): ?>
                                <span class="yii-debug-asset-chip yii-debug-asset-chip-css">
                                    <strong><?= $cssCount ?></strong> css
                                </span>
                            <?php endif; ?>
                            <?php if ($jsCount > 0): ?>
                                <span class="yii-debug-asset-chip yii-debug-asset-chip-js">
                                    <strong><?= $jsCount ?></strong> js
                                </span>
                            <?php endif; ?>
                            <?php if ($depsCount > 0): ?>
                                <span class="yii-debug-asset-chip yii-debug-asset-chip-deps">
                                    <strong><?= $depsCount ?></strong> dep<?= $depsCount === 1 ? '' : 's' ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </header>

                    <?php if ($hasFiles || $hasWiring || $hasDepends): ?>
                        <div class="yii-debug-asset-card-body" data-cols="<?= $bodyCols ?>">
                            <?php if ($hasFiles): ?>
                                <section class="yii-debug-asset-section">
                                    <h3 class="yii-debug-asset-section-title">Files</h3>
                                    <?php if ($css !== []): ?>
                                        <div class="yii-debug-asset-files">
                                            <?php foreach ($css as $cssFile): ?>
                                                <?php $label = $fileLabel($cssFile); ?>
                                                <div class="yii-debug-asset-file">
                                                    <span class="yii-debug-asset-file-type yii-debug-asset-file-type-css">.css</span>
                                                    <span class="yii-debug-asset-file-name" title="<?= Html::encode($label) ?>"><?= Html::encode($label) ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($js !== []): ?>
                                        <div class="yii-debug-asset-files">
                                            <?php foreach ($js as $jsFile): ?>
                                                <?php $label = $fileLabel($jsFile); ?>
                                                <div class="yii-debug-asset-file">
                                                    <span class="yii-debug-asset-file-type yii-debug-asset-file-type-js">.js</span>
                                                    <span class="yii-debug-asset-file-name" title="<?= Html::encode($label) ?>"><?= Html::encode($label) ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </section>
                            <?php endif; ?>

                            <?php if ($hasWiring || $hasDepends): ?>
                                <section class="yii-debug-asset-section">
                                    <h3 class="yii-debug-asset-section-title">Wiring</h3>
                                    <?php if ($hasWiring): ?>
                                        <dl class="yii-debug-asset-wiring">
                                            <?php if ($sourcePath !== ''): ?>
                                                <div class="yii-debug-asset-wiring-row">
                                                    <dt class="yii-debug-asset-wiring-label">source</dt>
                                                    <dd class="yii-debug-asset-wiring-value"><?= Html::encode($sourcePath) ?></dd>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($basePath !== ''): ?>
                                                <div class="yii-debug-asset-wiring-row">
                                                    <dt class="yii-debug-asset-wiring-label">base</dt>
                                                    <dd class="yii-debug-asset-wiring-value"><?= Html::encode($basePath) ?></dd>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($baseUrl !== ''): ?>
                                                <div class="yii-debug-asset-wiring-row">
                                                    <dt class="yii-debug-asset-wiring-label">url</dt>
                                                    <dd class="yii-debug-asset-wiring-value"><?= Html::encode($baseUrl) ?></dd>
                                                </div>
                                            <?php endif; ?>
                                        </dl>
                                    <?php endif; ?>

                                    <?php if ($hasDepends): ?>
                                        <div class="yii-debug-asset-depends">
                                            <span class="yii-debug-asset-depends-label">Depends on <?= $depsCount ?></span>
                                            <div class="yii-debug-asset-depends-list">
                                                <?php foreach ($depends as $dep): ?>
                                                    <?php
                                                    $depName = (string) $dep;
                                                    $depShort = $shortName($depName);
                                                    ?>
                                                    <a class="yii-debug-asset-depend"
                                                        href="#<?= Html::encode(Inflector::camel2id($depName)) ?>"
                                                        title="<?= Html::encode($depName) ?>">
                                                        <span class="yii-debug-asset-depend-icon" aria-hidden="true">↳</span>
                                                        <span class="yii-debug-asset-depend-name"><?= Html::encode($depShort) ?></span>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </section>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </article>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>
