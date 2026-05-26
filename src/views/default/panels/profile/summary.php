<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use yii\debug\html\defaults\{ToolbarBlock, ToolbarLabel};
use yii\debug\panels\ProfilingPanel;

/**
 * @var int $memory Peak memory consumption.
 * @var ProfilingPanel $panel Panel providing the toolbar summary data.
 * @var int $time Total request processing time.
 */
?>
<?= Div::tag()
    ->addDefaultProvider(ToolbarBlock::class)
    ->html(
        A::tag()
            ->content('Time ')
            ->href($panel->getUrl())
            ->html(
                Span::tag()
                    ->addDefaultProvider(ToolbarLabel::class)
                    ->class('yii-debug-toolbar-label-info')
                    ->content((string) $time),
            )
            ->title("Total request processing time was {$time}"),
        A::tag()
            ->content('Memory ')
            ->href($panel->getUrl())
            ->html(
                Span::tag()
                    ->addDefaultProvider(ToolbarLabel::class)
                    ->class('yii-debug-toolbar-label-info')
                    ->content((string) $memory),
            )
            ->title('Peak memory consumption'),
    );
