<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Helper\Encode;
use UIAwesome\Html\List\{Li, Ul};
use UIAwesome\Html\Palpable\A;
use yii\debug\panels\UserPanel;
use yii\web\View;

/**
 * @var UserPanel $panel Panel providing the detail content.
 * @var View $this View component instance.
 */
$encodedName = Encode::content($panel->getName());

$panelData = is_array($panel->data) ? $panel->data : [];

$identity = $panelData['identity'] ?? null;
?>
<?= H1::tag()
    ->class('yii-debug-sr-only')
    ->content($panel->getName()) ?>
<?php if ($identity === null): ?>
    Is guest.
    <?php return; ?>
<?php endif; ?>
<?php
$items = [
    'nav' => [$encodedName],
    'content' => [
        $this->render(
            '_identity',
            [
                'attributes' => $panelData['attributes'] ?? null,
                'identity' => $identity,
            ],
        ),
    ],
];

if (($panelData['rolesProvider'] ?? null) !== null || ($panelData['permissionsProvider'] ?? null) !== null) {
    $items['nav'][] = 'Roles and Permissions';

    $items['content'][] = $this->render(
        'roles',
        ['panel' => $panel],
    );
}

if ($panel->canSwitchUser()) {
    $items['nav'][] = "Switch {$encodedName}";

    $items['content'][] = $this->render(
        'switch',
        ['panel' => $panel],
    );
}

$navItems = [];

foreach ($items['nav'] as $k => $item) {
    $navItems[] = Li::tag()
        ->class('yii-debug-tab')
        ->html(
            A::tag()
                ->addAriaAttribute('controls', "u-tab-{$k}")
                ->addAriaAttribute('selected', $k === 0 ? 'true' : 'false')
                ->addAttribute('data-yii-debug-toggle', 'tab')
                ->addAttribute('role', 'tab')
                ->class($k === 0 ? 'yii-debug-tab-link is-active' : 'yii-debug-tab-link')
                ->href("#u-tab-{$k}")
                ->html($item),
        );
}

$contentPanels = [];

foreach ($items['content'] as $k => $item) {
    $contentPanels[] = Div::tag()
        ->class($k === 0 ? 'yii-debug-tab-panel is-active' : 'yii-debug-tab-panel')
        ->html($item)
        ->id("u-tab-{$k}");
}
?>
<?= Ul::tag()
    ->class('yii-debug-tabs')
    ->html(...$navItems) ?>
<?= Div::tag()
    ->class('yii-debug-tab-content')
    ->html(...$contentPanels);
