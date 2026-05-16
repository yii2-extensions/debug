<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use yii\debug\Panel;

/**
 * CustomPanel is a stub implementation of {@see Panel} for testing purposes.
 */
final class CustomPanel extends Panel
{
    public string|null $stubIcon = null;

    /**
     * @var array<int, array<string, mixed>>|null Items returned from {@see getToolbarItems()}; `null` hides the chip.
     */
    public array|null $stubItems = [];
    public string $stubName = '';
    public string $stubSummary = '';

    public function getName(): string
    {
        return $this->stubName;
    }

    public function getSummary(): string
    {
        return $this->stubSummary;
    }

    public function getToolbarIcon(): string|null
    {
        return $this->stubIcon;
    }

    protected function getToolbarItems(): array|null
    {
        return $this->stubItems;
    }
}
