<?php

declare(strict_types=1);

namespace yii\debug\helpers;

use UIAwesome\Html\Phrasing\{Span, Strong};

use function strrpos;
use function substr;

/**
 * Splits a fully-qualified class name into its short name and namespace prefix.
 *
 * Multiple renderers (asset, event, log, profile, queue) display the short class name next to a muted namespace prefix;
 * this helper keeps every view aligned on the same splitting rules and on the shared two-tone label markup.
 */
final class Fqcn
{
    /**
     * Returns the namespace prefix (everything before the last `\`, without trailing separator), or `''` when none is
     * present.
     */
    public static function namespacePart(string $fqcn): string
    {
        $position = strrpos($fqcn, '\\');

        return $position === false
            ? ''
            : substr($fqcn, 0, $position);
    }

    /**
     * Renders the shared two-tone label: a muted namespace prefix followed by a bold short name, with the full value
     * preserved in the `title` attribute for hover inspection.
     *
     * Method-suffixed values such as `yii\db\Command::query` keep the `Class::method` pair inside the bold segment;
     * values without a namespace render the bold segment only, and `''` collapses to an em dash. A `<wbr>` between
     * the two segments marks the namespace boundary as the preferred line-break opportunity.
     *
     * Usage example:
     * ```php
     * \yii\debug\helpers\Fqcn::renderLabel('yii\db\Command::query');
     * // <span title="yii\db\Command::query">
     * //     <span class="yii-debug-muted">yii\db\</span><wbr><strong>Command::query</strong>
     * // </span>
     * ```
     *
     * @param string $value Fully-qualified class name, `FQCN::method` pair, or plain category string.
     */
    public static function renderLabel(string $value): string
    {
        if ($value === '') {
            return '—';
        }

        $namespace = self::namespacePart($value);

        return Span::tag()
            ->title($value)
            ->html(
                $namespace !== ''
                    ? Span::tag()
                        ->class('yii-debug-muted')
                        ->content("{$namespace}\\")
                        ->render() . '<wbr>'
                    : '',
                Strong::tag()->content(self::shortName($value)),
            )
            ->render();
    }

    /**
     * Returns the segment after the last `\` separator, or the full `$fqcn` when no separator is present.
     */
    public static function shortName(string $fqcn): string
    {
        $position = strrpos($fqcn, '\\');

        return $position === false
            ? $fqcn
            : substr($fqcn, $position + 1);
    }
}
