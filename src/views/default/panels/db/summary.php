<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use yii\debug\html\defaults\{ToolbarBlock, ToolbarLabel};
use yii\debug\panels\DbPanel;

/**
 * @var int $excessiveCallerCount Number of callers exceeding the call threshold.
 * @var DbPanel $panel Panel providing the toolbar summary data.
 * @var int $queryCount Number of executed database queries.
 * @var int $queryTime Total database query execution time.
 */
$title = "Executed $queryCount database queries which took $queryTime.";
$warning = '';

if ($panel->isQueryCountCritical($queryCount)) {
    $warning .= "Too many queries, allowed count is {$panel->criticalQueryThreshold}.";
}

if ($excessiveCallerCount > 0) {
    $warning .= ($warning !== ''
        ? ' &#10;'
        : '') . $excessiveCallerCount . ' ' . ($excessiveCallerCount === 1 ? 'caller is' : 'callers are')
        . ' making too many calls.';
}

$block = '';

if ($queryCount > 0) {
    $anchor = A::tag()
        ->href($panel->getUrl())
        ->html(
            $panel->getSummaryName() . ' ',
            Span::tag()
                ->addDefaultProvider(ToolbarLabel::class)
                ->class('yii-debug-toolbar-label-info')
                ->content((string) $queryCount),
        )
        ->title($title);

    // Warning chip kept as raw HTML: its title embeds the '&#10;' newline entity and the body uses the '&#x26a0;'
    // glyph entity, both of which the builder's content/attribute encoding would turn into literal text.
    if ($warning !== '') {
        $anchor = $anchor->html("<span title=\"{$warning}\">&#x26a0;</span>");
    }

    $anchor = $anchor->html(
        Span::tag()
            ->addDefaultProvider(ToolbarLabel::class)
            ->content((string) $queryTime),
    );

    $block = Div::tag()
        ->addDefaultProvider(ToolbarBlock::class)
        ->html($anchor)
        ->render();
}
?>
<?= $block;
