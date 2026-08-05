<?php

declare(strict_types=1);

namespace yii\debug\helpers;

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Form\Button;
use UIAwesome\Html\Form\Values\ButtonType;

use function strlen;

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
     * Row count beyond which a table is worth collapsing.
     *
     * Table height tracks the number of rows, not the payload size: a handful of verbose rows reads fine, while many
     * terse ones still push the page past the viewport.
     */
    public const int ROW_THRESHOLD = 12;
    /**
     * Source length (bytes) beyond which a cell is worth collapsing.
     */
    public const int THRESHOLD = 600;

    /**
     * Wraps the content only when its source payload is long enough to be worth collapsing.
     *
     * The decision reads the raw source rather than the rendered markup, so highlighting or trace lists never tip a
     * short value over the threshold.
     *
     * Usage example:
     * ```php
     * \yii\debug\helpers\CellMore::clamp($highlightedSql, $row->query);
     * ```
     *
     * @param string $content Rendered cell HTML.
     * @param string $source Raw payload the content was rendered from.
     */
    public static function clamp(string $content, string $source): string
    {
        return strlen($source) > self::THRESHOLD ? self::wrap($content) : $content;
    }

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
                Button::tag()
                    ->addAriaAttribute('expanded', 'false')
                    ->addAttribute('data-yii-debug-toggle', 'cell-more')
                    ->class('yii-debug-cell-more-toggle')
                    ->content('[+] Show more')
                    ->type(ButtonType::BUTTON),
            )
            ->render();
    }
}
