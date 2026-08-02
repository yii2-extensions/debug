<?php

declare(strict_types=1);

namespace yii\debug\panels\config;

use yii\debug\storage\{ArrayPayloadSnapshot, PanelSnapshot};

/**
 * Canonical Configuration panel snapshot.
 */
final readonly class ConfigSnapshot implements PanelSnapshot
{
    use ArrayPayloadSnapshot;

    private const string KEY = 'data';

    /**
     * @return array<array-key, mixed> Captured application, PHP, and extension configuration.
     */
    public function data(): array
    {
        return $this->values();
    }
}
