<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use PHPForge\Debug\Storage\PanelSnapshot;
use yii\debug\collectors\Collector;

/**
 * Collector fixture counting its lifecycle hook invocations for {@see \yii\debug\tests\collectors\CollectorTest}.
 */
final class LifecycleCollector extends Collector
{
    public int $startCalls = 0;
    public int $stopCalls = 0;

    public function capture(): PanelSnapshot|null
    {
        return null;
    }

    public function id(): string
    {
        return 'stub';
    }

    protected function start(): void
    {
        $this->startCalls++;
    }

    protected function stop(): void
    {
        $this->stopCalls++;
    }
}
