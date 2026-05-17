<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use yii\debug\Panel;

/**
 * Stub panel whose `getToolbarData()` returns a chip envelope without 'id', 'title', or 'url' keys.
 */
final class MinimalToolbarPanel extends Panel
{
    public function getToolbarData(): array
    {
        return ['chip' => 'minimal'];
    }
}
