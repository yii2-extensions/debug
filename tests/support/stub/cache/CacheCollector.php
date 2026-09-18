<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\cache;

use PHPForge\Debug\CollectorInterface;

/**
 * Buffers application-owned cache operations for the request the debugger observes.
 */
final class CacheCollector implements CollectorInterface
{
    /**
     * @var list<array{string, string, string}> Operations recorded since the last startup.
     */
    private array $operations = [];

    /**
     * @var bool Whether request-scoped collection is running.
     */
    private bool $started = false;

    /**
     * @return array<string, mixed>|null Recorded operations while started; `null` otherwise.
     */
    public function capture(): array|null
    {
        return $this->started ? ['operations' => $this->operations] : null;
    }

    public function id(): string
    {
        return 'cache';
    }

    /**
     * Appends one cache operation while collection is running.
     *
     * @param string $operation Operation name, such as `get` or `set`.
     * @param string $key Cache key the operation addressed.
     * @param string $result Outcome of the operation, such as `hit`, `miss`, or `stored`.
     */
    public function record(string $operation, string $key, string $result): void
    {
        if ($this->started) {
            $this->operations[] = [$operation, $key, $result];
        }
    }

    public function shutdown(): void
    {
        $this->started = false;
        $this->operations = [];
    }

    public function startup(): void
    {
        $this->started = true;
    }
}
