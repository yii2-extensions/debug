<?php

declare(strict_types=1);

namespace yii\debug\html\defaults;

/**
 * Supplies the shared `yii-debug-toolbar-label` base class for toolbar labels.
 *
 * Usage example:
 * ```php
 * \UIAwesome\Html\Phrasing\Span::tag(\yii\debug\html\defaults\ToolbarLabel::DEFINITION)
 *     ->class('yii-debug-toolbar-label-error')
 *     ->content('error');
 * ```
 */
final class ToolbarLabel
{
    /**
     * Base-class definition applied as `tag()` defaults when building toolbar labels.
     */
    public const array DEFINITION = ['class' => 'yii-debug-toolbar-label'];
}
