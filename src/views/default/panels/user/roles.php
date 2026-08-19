<?php

declare(strict_types=1);

use PHPForge\Debug\Panel\User\UserRbacRenderer;
use yii\debug\GridViewConfig;
use yii\debug\panels\UserPanel;
use yii\grid\GridView;

/** @var UserPanel $panel User panel providing role and permission data. */
$columns = [
    'name',
    'description',
    'ruleName',
    'data',
    'createdAt:datetime',
    'updatedAt:datetime',
];

$rolesProvider = $panel->getRolesProvider();
$permissionsProvider = $panel->getPermissionsProvider();

?>
<?= UserRbacRenderer::render(
    $rolesProvider === null
        ? null
        : GridView::widget(
        [
            ...GridViewConfig::defaults(),
            'dataProvider' => $rolesProvider,
            'columns' => $columns,
        ],
    ),
    $permissionsProvider === null
        ? null
        : GridView::widget(
        [
            ...GridViewConfig::defaults(),
            'dataProvider' => $permissionsProvider,
            'columns' => $columns,
        ],
    ),
) ?>
