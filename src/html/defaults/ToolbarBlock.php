<?php

declare(strict_types=1);

namespace yii\debug\html\defaults;

/**
 * Supplies the shared `yii-debug-toolbar-block` base class for toolbar blocks.
 *
 * Usage example:
 * ```php
 * \UIAwesome\Html\Flow\Div::tag(\yii\debug\html\defaults\ToolbarBlock::DEFINITION)
 *     ->class('yii-debug-toolbar-title');
 * ```
 */
final class ToolbarBlock
{
    /**
     * Base-class definition applied as `tag()` defaults when building toolbar blocks.
     */
    public const array DEFINITION = ['class' => 'yii-debug-toolbar-block'];
}
