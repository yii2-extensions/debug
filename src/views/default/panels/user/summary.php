<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use yii\debug\html\defaults\{ToolbarBlock, ToolbarLabel};
use yii\debug\panels\UserPanel;
use yii\web\View;

/**
 * @var UserPanel $panel Panel providing the toolbar summary data.
 * @var View $this View component instance.
 */
$data = is_array($panel->data) ? $panel->data : [];

$id = $data['id'] ?? null;
?>
<?php if ($id === null): ?>
    <?= Div::tag()
        ->addDefaultProvider(ToolbarBlock::class)
        ->html(
            A::tag()
                ->href($panel->getUrl())
                ->html(
                    Span::tag()
                        ->addDefaultProvider(ToolbarLabel::class)
                        ->content('Guest'),
                ),
        ) ?>
    <?php return; ?>
<?php endif; ?>
<?php
$idLabel = (string) $id;

$user = $panel->getUser();

$isGuest = $user === null || $user->isGuest;

$isMainUser = $panel->userSwitch === null || $panel->userSwitch->isMainUser();

$anchor = A::tag()
    ->content($panel->getName() . ($isGuest || $isMainUser ? ' ' : ' switching '))
    ->href($panel->getUrl())
    ->html(
        Span::tag()
            ->addDefaultProvider(ToolbarLabel::class)
            ->class($isGuest || $isMainUser ? 'yii-debug-toolbar-label-info' : 'yii-debug-toolbar-label-warning')
            ->content($idLabel),
    );

if ($panel->canSwitchUser()) {
    $anchor = $anchor->html(
        Span::tag()
            ->class('yii-debug-toolbar-switch-icon yii-debug-toolbar-userswitch')
            ->id('yii-debug-toolbar-switch-users'),
    );
}
?>
<?= Div::tag()
    ->addDefaultProvider(ToolbarBlock::class)
    ->html($anchor);
