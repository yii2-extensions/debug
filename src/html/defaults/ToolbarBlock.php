<?php

declare(strict_types=1);

namespace yii\debug\html\defaults;

use UIAwesome\Html\Core\Base\BaseTag;
use UIAwesome\Html\Core\Provider\DefaultsProviderInterface;

/**
 * Supplies the shared `yii-debug-toolbar-block` base class for toolbar blocks.
 *
 * Usage example:
 * ```php
 * \UIAwesome\Html\Flow\Div::tag()
 *     ->addDefaultProvider(\yii\debug\html\defaults\ToolbarBlock::class)
 *     ->class('yii-debug-toolbar-title');
 * ```
 */
final class ToolbarBlock implements DefaultsProviderInterface
{
    /**
     * Returns the base-class definition merged into the block tag at render time.
     *
     * @param BaseTag $tag Tag the provider is decorating.
     *
     * @return array<string, mixed> Method-call definitions applied to the tag.
     */
    public function getDefaults(BaseTag $tag): array
    {
        return ['class' => 'yii-debug-toolbar-block'];
    }
}
