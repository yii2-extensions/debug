<?php

declare(strict_types=1);

namespace yii\debug\helpers;

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Palpable\A;

/**
 * Wraps long grid-cell content in a collapsible clamp with an expand/collapse pill toggle.
 *
 * The body collapses to a few lines through a CSS max-height clamp — the markup is never truncated server side, so
 * any cell payload (plain text, highlighted SQL, trace lists) stays intact. The `debug.min.js` `cell-more` delegation
 * flips the `is-open` state and swaps the toggle label.
 */
final class CellMore
{
    /**
     * Wraps the rendered cell content in the collapsible clamp container.
     *
     * Usage example:
     * ```php
     * \yii\debug\helpers\CellMore::wrap($renderedCellHtml);
     * ```
     *
     * @param string $content Rendered cell HTML to clamp; emitted verbatim inside the body container.
     */
    public static function wrap(string $content): string
    {
        return Div::tag()
            ->class('yii-debug-cell-more')
            ->html(
                Div::tag()
                    ->class('yii-debug-cell-more-body')
                    ->html($content),
                A::tag()
                    ->addAriaAttribute('expanded', 'false')
                    ->addAttribute('data-yii-debug-toggle', 'cell-more')
                    ->class('yii-debug-cell-more-toggle')
                    ->content('[+] Show more')
                    ->href('javascript:;'),
            )
            ->render();
    }
}
