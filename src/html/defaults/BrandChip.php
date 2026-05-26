<?php

declare(strict_types=1);

namespace yii\debug\html\defaults;

use UIAwesome\Html\Core\Base\BaseTag;
use UIAwesome\Html\Core\Provider\DefaultsProviderInterface;

/**
 * Supplies the shared `yii-debug-brand-chip` base class for brand-bar chips.
 *
 * Usage example:
 * ```php
 * \UIAwesome\Html\Palpable\A::tag()
 *     ->addDefaultProvider(\yii\debug\html\defaults\BrandChip::class)
 *     ->class('yii-debug-brand-chip-config');
 * ```
 */
final class BrandChip implements DefaultsProviderInterface
{
    /**
     * Returns the base-class definition merged into the chip tag at render time.
     *
     * @param BaseTag $tag Tag the provider is decorating.
     *
     * @return array<string, mixed> Method-call definitions applied to the tag.
     */
    public function getDefaults(BaseTag $tag): array
    {
        return ['class' => 'yii-debug-brand-chip'];
    }
}
