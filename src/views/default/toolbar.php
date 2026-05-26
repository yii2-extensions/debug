<?php

declare(strict_types=1);

use UIAwesome\Html\Embedded\{Iframe, Img};
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use UIAwesome\Html\Table\{Table, Tbody, Th, Thead, Tr};
use yii\debug\html\defaults\{ToolbarBlock, ToolbarLabel};
use yii\debug\{Module, Panel};
use yii\helpers\Url;
use yii\web\View;

/**
 * @var int $defaultHeight Default toolbar height, in pixels.
 * @var array<string, Panel> $panels Debug panels keyed by id.
 * @var string $position Toolbar position modifier.
 * @var string $tag Active request tag.
 * @var View $this View component instance.
 */
?>
<div id="yii-debug-toolbar" class="yii-debug-toolbar yii-debug-toolbar-position-<?= $position ?>" data-height="<?= $defaultHeight ?>">
    <div class="yii-debug-toolbar-resize-handle"></div>
    <div class="yii-debug-toolbar-bar">
        <?= Div::tag()
            ->addDefaultProvider(ToolbarBlock::class)
            ->class('yii-debug-toolbar-title')
            ->html(
                A::tag()
                    ->href(Url::to(['index']))
                    ->html(
                        Img::tag()
                            ->width(30)
                            ->height(30)
                            ->alt('Yii')
                            ->src(Module::getYiiLogo())
                    )
            ) ?>

        <div class="yii-debug-toolbar-block yii-debug-toolbar-ajax" style="display: none">
            AJAX
            <?= Span::tag()
                ->addDefaultProvider(ToolbarLabel::class)
                ->class('yii-debug-toolbar-ajax-counter')
                ->content('0') ?>
            <div class="yii-debug-toolbar-ajax-info">
                <?= Table::tag()
                    ->html(
                        Thead::tag()
                            ->html(
                                Tr::tag()
                                    ->html(
                                        Th::tag()->content('Method'),
                                        Th::tag()->content('Status'),
                                        Th::tag()->content('URL'),
                                        Th::tag()->content('Time'),
                                        Th::tag()->content('Profile'),
                                    )
                            ),
                        Tbody::tag()
                            ->class('yii-debug-toolbar-ajax-requests'),
                    ) ?>
            </div>
        </div>

        <?php foreach ($panels as $panel): ?>
            <?php if ($panel->hasError()): ?>
                <?php $panelError = $panel->getError(); ?>
                <?= Div::tag()
                    ->addDefaultProvider(ToolbarBlock::class)
                    ->html(
                        A::tag()
                            ->href($panel->getUrl())
                            ->title($panelError?->getMessage() ?? '')
                            ->content($panel->getName())
                            ->html(
                                ' ',
                                Span::tag()
                                    ->addDefaultProvider(ToolbarLabel::class)
                                    ->class('yii-debug-toolbar-label-error')
                                    ->content('error'),
                            )
                    ) ?>
            <?php else: ?>
                <?= $panel->getSummary() ?>
            <?php endif; ?>
        <?php endforeach; ?>

        <div class="yii-debug-toolbar-block-last">

        </div>
        <?= A::tag()
            ->class('yii-debug-toolbar-external')
            ->href('#')
            ->html(
                Span::tag()->class('yii-debug-toolbar-external-icon')
            )
            ->target('_blank'); ?>
        <?= Span::tag()
            ->class('yii-debug-toolbar-toggle')
            ->html(
                Span::tag()->class('yii-debug-toolbar-toggle-icon')
            ) ?>
    </div>

    <div class="yii-debug-toolbar-view">
        <?= Iframe::tag()
            ->src('about:blank')
            ->title('Yii2 debug bar') ?>
    </div>
</div>
