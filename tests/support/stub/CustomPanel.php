<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use Override;
use yii\debug\Panel;

/**
 * Stub implementation of {@see Panel} for testing purposes.
 */
final class CustomPanel extends Panel
{
    public string|null $stubIcon = null;

    /**
     * @var array<int, array<string, mixed>> Items returned from {@see getToolbarItems()}; `[]` hides the chip.
     */
    public array $stubItems = [];
    public string $stubName = '';

    #[Override]
    public function getName(): string
    {
        return $this->stubName;
    }

    #[Override]
    public function getToolbarIcon(): string|null
    {
        return $this->stubIcon;
    }

    #[Override]
    protected function getToolbarItems(): array
    {
        return $this->stubItems;
    }
}
