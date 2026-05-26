<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use yii\debug\html\defaults\{ToolbarBlock, ToolbarLabel};
use yii\debug\panels\RouterPanel;

/** @var RouterPanel $panel Panel providing the toolbar summary data. */
$data = is_array($panel->data) ? $panel->data : [];
$action = is_string($data['action'] ?? null) ? $data['action'] : '';
$route = is_string($data['route'] ?? null) ? $data['route'] : '';
?>
<?= Div::tag()
    ->addDefaultProvider(ToolbarBlock::class)
    ->html(
        A::tag()
            ->content('Route ')
            ->href($panel->getUrl())
            ->html(
                Span::tag()
                    ->addDefaultProvider(ToolbarLabel::class)
                    ->content($route),
            )
            ->title("Action: {$action}"),
    ) ?>
