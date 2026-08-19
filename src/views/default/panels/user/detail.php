<?php

declare(strict_types=1);

use UIAwesome\Html\Heading\H1;
use yii\debug\panels\UserPanel;
use PHPForge\Debug\Helper\Tabs;
use PHPForge\Debug\Panel\User\UserGuestRenderer;
use yii\web\View;

/**
 * @var UserPanel $panel Panel providing the detail content.
 * @var View $this View component instance.
 */
$panelData = $panel->getSnapshotData();

$identity = $panelData['identity'] ?? null;
?>
<?= H1::tag()
    ->class('yii-debug-sr-only')
    ->content($panel->getName()) ?>
<?php if ($identity === null): ?>
    <?= UserGuestRenderer::render() ?>
    <?php return; ?>
<?php endif; ?>
<?php
$tabs = [
    [
        'label' => $panel->getName(),
        'content' => $this->render(
            '_identity',
            [
                'attributes' => $panelData['attributes'] ?? null,
                'identity' => $identity,
            ],
        ),
    ],
];

if ($panel->getRolesProvider() !== null || $panel->getPermissionsProvider() !== null) {
    $tabs[] = [
        'label' => 'Roles and Permissions',
        'content' => $this->render('roles', ['panel' => $panel]),
    ];
}

if ($panel->canSwitchUser()) {
    $tabs[] = [
        'label' => 'Switch ' . $panel->getName(),
        'content' => $this->render('switch', ['panel' => $panel]),
    ];
}
?>
<?= Tabs::render('user', 'User data', $tabs);
