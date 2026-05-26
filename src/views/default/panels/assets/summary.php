<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use yii\debug\html\defaults\{ToolbarBlock, ToolbarLabel};
use yii\debug\panels\AssetPanel;

/** @var AssetPanel $panel Panel providing the toolbar summary data. */
$bundles = is_array($panel->data) ? $panel->data : [];
?>
<?= Div::tag()
    ->addDefaultProvider(ToolbarBlock::class)
    ->html(
        A::tag()
            ->content('Asset Bundles ')
            ->href($panel->getUrl())
            ->html(
                Span::tag()
                    ->addDefaultProvider(ToolbarLabel::class)
                    ->class('yii-debug-toolbar-label-info')
                    ->content((string) count($bundles))
            )
            ->title('Number of asset bundles loaded')
    ) ?>
