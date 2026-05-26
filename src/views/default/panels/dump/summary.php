<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use yii\debug\html\defaults\{ToolbarBlock, ToolbarLabel};
use yii\debug\panels\AssetPanel;

/** @var AssetPanel $panel Panel providing the toolbar summary data. */
$dumps = is_array($panel->data) ? $panel->data : [];
?>
<?php if ($dumps !== []): ?>
<?= Div::tag()
    ->addDefaultProvider(ToolbarBlock::class)
    ->html(
        A::tag()
            ->content('Dump ')
            ->href($panel->getUrl())
            ->html(
                Span::tag()
                    ->addDefaultProvider(ToolbarLabel::class)
                    ->class('yii-debug-toolbar-label-info')
                    ->content((string) count($dumps)),
            )
            ->title('Number of dumped variables')
    ) ?>
<?php endif;
