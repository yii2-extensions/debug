<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use yii\debug\html\defaults\{ToolbarBlock, ToolbarLabel};
use yii\debug\panels\MailPanel;

/**
 * @var int $mailCount Number of captured mail messages.
 * @var MailPanel $panel Panel providing the toolbar summary data.
 */
?>
<?= Div::tag(ToolbarBlock::DEFINITION)
    ->html(
        A::tag()
            ->content('Mail ')
            ->href($panel->getUrl())
            ->html(
                Span::tag(ToolbarLabel::DEFINITION)
                    ->content((string) $mailCount),
            ),
    ) ?>
