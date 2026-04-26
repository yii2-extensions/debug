<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var yii\debug\models\router\ActionRoutes $actionRoutes */
?>
<?php if (count($actionRoutes->routes) === 0): ?>
    <h3>No actions configured.</h3>
<?php else: ?>
    <div class="yii-debug-table-wrap">
        <table class="yii-debug-table">
            <thead>
            <tr>
                <th>#</th>
                <th>Action</th>
                <th>Route</th>
                <th>First Matching Rule</th>
                <th>Rules Tested</th>
            </tr>
            </thead>
            <tbody>
                <?php $i = 1;
    foreach ($actionRoutes->routes as $action => $route): ?>
                    <tr>
                        <td><?= $i++; ?></td>
                        <td><?= Html::encode($action) ?></td>
                        <td><?= Html::encode($route['route']) ?></td>
                        <td><?= Html::encode($route['rule'] ?? '') ?></td>
                        <td><?= Html::encode((string) $route['count']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif;
