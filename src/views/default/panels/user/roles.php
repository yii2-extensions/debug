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

if ($panel->data['rolesProvider']) {
    echo '<h2>Roles</h2>';

    echo GridView::widget(array_merge(GridViewConfig::defaults(), [
        'dataProvider' => $panel->data['rolesProvider'],
        'columns' => $columns,
    ]));
}

if ($panel->data['permissionsProvider']) {
    echo '<h2>Permissions</h2>';

    echo GridView::widget(array_merge(GridViewConfig::defaults(), [
        'dataProvider' => $panel->data['permissionsProvider'],
        'columns' => $columns,
    ]));
}
