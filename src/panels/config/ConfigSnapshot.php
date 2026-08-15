<?php

declare(strict_types=1);

namespace yii\debug\panels\config;

use PHPForge\Debug\Storage\{ArrayPayloadSnapshot, PanelSnapshot};

/**
 * Canonical Configuration panel snapshot.
 */
final readonly class ConfigSnapshot implements PanelSnapshot
{
    use ArrayPayloadSnapshot;

    /**
     * @return array<array-key, mixed> Captured application, PHP, and extension configuration.
     */
    public function data(): array
    {
        return $this->values();
    }

    protected static function payloadKey(): string
    {
        return 'data';
    }
}
