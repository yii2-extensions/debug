<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var string $caption */
/** @var array $values */
?>

<h3><?= $caption ?></h3>

<?php if (empty($values)): ?>
    <p>Empty.</p>

<?php else: ?>
    <div class="yii-debug-table-wrap">
        <table class="yii-debug-table" style="table-layout: fixed;">
            <thead>
            <tr>
                <th>Name</th>
                <th>Value</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($values as $name => $value): ?>
                <tr>
                    <th style="white-space: normal"><?= Html::encode($name) ?></th>
                    <td style="overflow:auto"><?= Html::encode($value) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
