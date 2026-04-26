<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Inflector;

/** @var yii\debug\panels\AssetPanel $panel */

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
            <span class="yii-debug-asset-stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m12 3 8 4.5v9L12 21l-8-4.5v-9z"/>
                    <path d="M12 12 4 7.5"/>
                    <path d="m12 12 8-4.5"/>
                    <path d="M12 12v9"/>
                </svg>
            </span>
            <strong class="yii-debug-asset-stat-value"><?= $totalBundles ?></strong>
            <span class="yii-debug-asset-stat-label">bundle<?= $totalBundles === 1 ? '' : 's' ?></span>
        </div>
        <div class="yii-debug-asset-stat" data-kind="css">
            <span class="yii-debug-asset-stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16l-1.5 16-6.5 2-6.5-2z"/>
                    <path d="M8 8h8l-.4 4-3.6 1.1-3.6-1.1L8.2 10"/>
                </svg>
            </span>
            <strong class="yii-debug-asset-stat-value"><?= $totalCss ?></strong>
            <span class="yii-debug-asset-stat-label">css</span>
        </div>
        <div class="yii-debug-asset-stat" data-kind="js">
            <span class="yii-debug-asset-stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M13 2 4 14h7l-2 8 9-12h-7z"/>
                </svg>
            </span>
            <strong class="yii-debug-asset-stat-value"><?= $totalJs ?></strong>
            <span class="yii-debug-asset-stat-label">js</span>
        </div>
        <div class="yii-debug-asset-stat" data-kind="deps">
            <span class="yii-debug-asset-stat-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10 14a4 4 0 0 0 5.66 0l3-3a4 4 0 0 0-5.66-5.66l-1 1"/>
                    <path d="M14 10a4 4 0 0 0-5.66 0l-3 3a4 4 0 0 0 5.66 5.66l1-1"/>
                </svg>
            </span>
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
                        <span class="yii-debug-asset-card-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m12 3 8 4.5v9L12 21l-8-4.5v-9z"/>
                                <path d="M12 12 4 7.5"/>
                                <path d="m12 12 8-4.5"/>
                                <path d="M12 12v9"/>
                            </svg>
                        </span>
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
