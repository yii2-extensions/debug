<?php

declare(strict_types=1);

namespace yii\debug\html\defaults;

use UIAwesome\Html\Core\Base\BaseTag;
use UIAwesome\Html\Core\Provider\DefaultsProviderInterface;

/**
 * Supplies the shared `yii-debug-toolbar-label` base class for toolbar labels.
 *
 * Usage example:
 * ```php
 * \UIAwesome\Html\Phrasing\Span::tag()
 *     ->addDefaultProvider(\yii\debug\html\defaults\ToolbarLabel::class)
 *     ->class('yii-debug-toolbar-label-error')
 *     ->content('error');
 * ```
 */
final class ToolbarLabel implements DefaultsProviderInterface
{
    /**
     * Returns the base-class definition merged into the label tag at render time.
     *
     * @param BaseTag $tag Tag the provider is decorating.
     *
     * @return array<string, mixed> Method-call definitions applied to the tag.
     */
    public function getDefaults(BaseTag $tag): array
    {
        return ['class' => 'yii-debug-toolbar-label'];
    }
}
