<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\P;
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Phrasing\Code;
use yii\debug\helpers\EmptyState;
use yii\debug\panels\UserPanel;
use yii\debug\widgets\Tabs;
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
    <?= EmptyState::card(
        'No user authenticated in this request',
        P::tag()
            ->html(
                'The request was served to a guest — ',
                Code::tag()->content('Yii::$app->user'),
                ' held no identity when the response was sent, so there are no identity attributes, roles, or permissions to inspect.',
            ),
        P::tag()
            ->html(
                'Sign in and reload to inspect the identity here; for authenticated sessions this panel can also switch identities on the fly when the module access rules allow it (',
                Code::tag()->content('ruleUserSwitch'),
                ').',
            ),
    ) ?>
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
