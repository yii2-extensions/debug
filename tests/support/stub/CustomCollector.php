<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use PHPForge\Debug\Collector\CollectorInterface;
use PHPForge\Debug\Storage\PanelSnapshot;
use RuntimeException;

/**
 * Provides configurable custom collector behavior for Yii2 integration tests.
 */
final class CustomCollector implements CollectorInterface
{
    public string $collectorId = 'app.example';
    public bool $failCapture = false;
    public int $shutdownCount = 0;
    public int $startupCount = 0;
    public mixed $value = 42;

    public function capture(): PanelSnapshot
    {
        if ($this->failCapture) {
            throw new RuntimeException('Custom collector failed.');
        }

        return StubSnapshot::capture(['value' => $this->value]);
    }

    public function id(): string
    {
        return $this->collectorId;
    }

    public function shutdown(): void
    {
        ++$this->shutdownCount;
    }

    public function startup(): void
    {
        ++$this->startupCount;
    }
}
