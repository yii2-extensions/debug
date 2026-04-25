<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\VarDumper;

/** @var string $caption */
/** @var array $values */
/** @var bool $filterable */

$filterable = $filterable ?? false;
$rowCount = count($values);
?>
<header class="yii-debug-section-header">
    <h3><?= Html::encode($caption) ?></h3>
    <?php if ($filterable && $rowCount > 0): ?>
        <input
            type="search"
            class="yii-debug-filter-input"
            data-yii-debug-filter
            placeholder="Filter…"
            aria-label="Filter <?= Html::encode($caption) ?>"
        >
    <?php endif; ?>
</header>

<?php if (empty($values)): ?>
    <p class="yii-debug-table-empty">No data</p>

<?php else: ?>
    <div class="yii-debug-table-wrap"<?= $filterable ? ' data-yii-debug-filter-target' : '' ?>>
        <table class="yii-debug-table yii-debug-table-mono" style="table-layout: fixed;">
            <thead>
            <tr>
                <th>Name</th>
                <th>Value</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($values as $name => $value): ?>
                <tr>
                    <th><?= Html::encode($name) ?></th>
                    <td><?= htmlspecialchars(VarDumper::dumpAsString($value), ENT_QUOTES | ENT_SUBSTITUTE, \Yii::$app->charset, true) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>
