<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\VarDumper;

/** @var string $caption */
/** @var array $values */
?>
<h3><?= $caption ?></h3>

<?php if (empty($values)): ?>
    <p>Empty.</p>

<?php else: ?>
    <div class="yii-debug-table-wrap">
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
