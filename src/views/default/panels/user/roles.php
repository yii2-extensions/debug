<?php

declare(strict_types=1);

use UIAwesome\Html\Heading\H3;
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

$data = is_array($panel->data) ? $panel->data : [];

$rolesProvider = $data['rolesProvider'] ?? null;
$permissionsProvider = $data['permissionsProvider'] ?? null;

?>
<?php if ($rolesProvider !== null): ?>
    <?= H3::tag()->content('User') ?>
    <?= GridView::widget(
        [
            ...GridViewConfig::defaults(),
            'dataProvider' => $rolesProvider,
            'columns' => $columns,
        ],
    ) ?>
<?php endif; ?>
<?php if ($permissionsProvider !== null): ?>
    <?= H3::tag()->content('Permissions') ?>
    <?= GridView::widget(
        [
            ...GridViewConfig::defaults(),
            'dataProvider' => $permissionsProvider,
            'columns' => $columns,
        ],
    ) ?>
<?php endif;
