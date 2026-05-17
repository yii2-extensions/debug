<?php

declare(strict_types=1);

use yii\debug\GridViewConfig;
use yii\grid\GridView;

/** @var yii\debug\panels\UserPanel $panel */

$columns = [
    'name',
    'description',
    'ruleName',
    'data',
    'createdAt:datetime',
    'updatedAt:datetime',
];

$data = is_array($panel->data) ? $panel->data : [];

$rolesProvider = $data['rolesProvider'] ?? null;
$permissionsProvider = $data['permissionsProvider'] ?? null;

if ($rolesProvider !== null) {
    echo '<h3>Roles</h3>';
    echo GridView::widget(
        [
            ...GridViewConfig::defaults(),
            'dataProvider' => $rolesProvider,
            'columns' => $columns,
        ],
    );
}

if ($permissionsProvider !== null) {
    echo '<h3>Permissions</h3>';
    echo GridView::widget(
        [
            ...GridViewConfig::defaults(),
            'dataProvider' => $permissionsProvider,
            'columns' => $columns,
        ],
    );
}
