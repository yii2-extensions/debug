<?php

declare(strict_types=1);

namespace yii\debug\html\defaults;

/**
 * Supplies the shared `yii-debug-brand-chip` base class for brand-bar chips.
 */
final class BrandChip
{
    /**
     * Base-class definition applied as `tag()` defaults when building brand-bar chips.
     */
    public const array DEFINITION = ['class' => 'yii-debug-brand-chip'];
}
