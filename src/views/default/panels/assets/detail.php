<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Inflector;

/** @var yii\debug\panels\AssetPanel $panel */
?>
<h1 class="yii-debug-sr-only">Asset Bundles</h1>

<?php if (empty($panel->data)) {
    echo '<p>No asset bundle was used.</p>';
    return;
} ?>
<div class="yii-debug-table-wrap">
    <table class="yii-debug-table">
        <caption>
            <p>Total <b><?= count($panel->data) ?></b> asset bundles were loaded.</p>
        </caption>
        <?php
        foreach ($panel->data as $name => $bundle) {
            ?>
            <thead>
            <tr>
                <td colspan="2"><h3 id="<?= Inflector::camel2id($name) ?>"><?= $name ?></h3></td>
            </tr>
            </thead>
            <tbody>
            <tr>
                <th>sourcePath</th>
                <td><?= Html::encode($bundle['sourcePath'] !== null ? $bundle['sourcePath'] : $bundle['basePath']) ?></td>
            </tr>
            <?php if ($bundle['basePath'] !== null): ?>
                <tr>
                    <th>basePath</th>
                    <td><?= Html::encode($bundle['basePath']) ?></td>
                </tr>
            <?php endif; ?>
            <?php if ($bundle['baseUrl'] !== null): ?>
                <tr>
                    <th>baseUrl</th>
                    <td><?= Html::encode($bundle['baseUrl']) ?></td>
                </tr>
            <?php endif; ?>
            <?php if (!empty($bundle['css'])): ?>
                <tr>
                    <th>css</th>
                    <td class="ws-normal">
                        <?= Html::ul($bundle['css'], [
                            'class' => 'yii-debug-list',
                            'item' => function ($item) {
                                if (is_array($item)) {
                                    $item = reset($item);
                                }
                                return Html::tag('li', Html::encode($item));
                            },
                        ]) ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php if (!empty($bundle['js'])): ?>
                <tr>
                    <th>js</th>
                    <td class="ws-normal">
                        <?= Html::ul($bundle['js'], [
                            'class' => 'yii-debug-list',
                            'item' => function ($item) {
                                if (is_array($item)) {
                                    $item = reset($item);
                                }
                                return Html::tag('li', Html::encode($item));
                            },
                        ]) ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php if (!empty($bundle['depends'])): ?>
                <tr>
                    <th>depends</th>
                    <td class="ws-normal">
                        <ul class="yii-debug-list">
                            <?php foreach ($bundle['depends'] as $depend): ?>
                                <li><?= Html::a($depend, '#' . Inflector::camel2id($depend)) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
            <?php
        }
?>
    </table>
</div>
