<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use yii\debug\html\defaults\{ToolbarBlock, ToolbarLabel};
use yii\debug\panels\ConfigPanel;

/** @var ConfigPanel $panel Panel providing the toolbar summary data. */
$data = is_array($panel->data) ? $panel->data : [];
$application = is_array($data['application'] ?? null) ? $data['application'] : [];
$php = is_array($data['php'] ?? null) ? $data['php'] : [];
$yiiVersion = is_string($application['yii'] ?? null) ? $application['yii'] : '';
$phpVersion = is_string($php['version'] ?? null) ? $php['version'] : '';
?>
<?= Div::tag()
    ->addDefaultProvider(ToolbarBlock::class)
    ->html(
        A::tag()
            ->href($panel->getUrl())
            ->html(
                Span::tag()
                    ->addDefaultProvider(ToolbarLabel::class)
                    ->content($yiiVersion),
                ' PHP ',
                Span::tag()
                    ->addDefaultProvider(ToolbarLabel::class)
                    ->content($phpVersion),
            ),
    ) ?>
