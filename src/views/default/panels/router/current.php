<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var yii\debug\models\router\CurrentRoute $currentRoute */
?>
<h3>
    <?= Yii::$app->i18n->format(
        '{rulesTested, plural, =0{} =1{Tested # rule} other{Tested # rules}}{hasMatch, plural, =0{} other{ before match}}.',
        [
            'rulesTested' => $currentRoute->count,
            'hasMatch' => (int) $currentRoute->hasMatch,
        ],
        'en_US',
    ); ?>
</h3>

<?php if ($currentRoute->message !== null): ?>
    <div class="yii-debug-callout yii-debug-callout-info">
        <?= Html::encode($currentRoute->message) ?>
    </div>
<?php endif; ?>
<?php if (count($currentRoute->logs)): ?>
    <div class="yii-debug-table-wrap">
        <table class="yii-debug-table">
            <thead>
            <tr>
                <th>#</th>
                <th>Rule</th>
                <th>Parent</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($currentRoute->logs as $i => $log): ?>
                <tr<?= $log['match'] ? ' class="yii-debug-row-success"' : '' ?>>
                    <td><?= $i + 1; ?></td>
                    <td><?= Html::encode($log['rule']) ?></td>
                    <td><?= Html::encode($log['parent'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif;
