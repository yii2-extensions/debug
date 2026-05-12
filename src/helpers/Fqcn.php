<?php

declare(strict_types=1);

namespace yii\debug\helpers;

use function strrpos;
use function substr;

/**
 * Splits a fully-qualified class name into its short name and namespace prefix.
 *
 * Multiple renderers (asset, queue) display the short class name next to a muted namespace prefix; this helper keeps
 * both views aligned on the same splitting rules.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
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

        return $position === false ? '' : substr($fqcn, 0, $position);
    }
    /**
     * Returns the segment after the last `\` separator, or the full `$fqcn` when no separator is present.
     */
    public static function shortName(string $fqcn): string
    {
        $position = strrpos($fqcn, '\\');

        return $position === false ? $fqcn : substr($fqcn, $position + 1);
    }
}
