<?php

declare(strict_types=1);

namespace yii\inertia;

use yii\base\Component;

/**
 * Stand-in for the `yii2-extensions/inertia` manager component, loaded only when the real package is absent.
 */
final class Manager extends Component
{
    /**
     * @var array<string, mixed> Shared props exposed to every page, mirroring the real manager's property.
     */
    public array $shared = [];

    /**
     * @return array<string, mixed> Shared props exposed to every page.
     */
    public function getShared(): array
    {
        return $this->shared;
    }
}
