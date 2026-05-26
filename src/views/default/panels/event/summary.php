<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use yii\debug\html\defaults\{ToolbarBlock, ToolbarLabel};
use yii\debug\panels\EventPanel;

/**
 * @var int $eventCount Number of triggered events.
 * @var EventPanel $panel Panel providing the toolbar summary data.
 */
?>
<?= Div::tag()
    ->addDefaultProvider(ToolbarBlock::class)
    ->html(
        A::tag()
            ->content('Events ')
            ->href($panel->getUrl())
            ->html(
                Span::tag()
                    ->addDefaultProvider(ToolbarLabel::class)
                    ->content((string) $eventCount),
            )
    );
