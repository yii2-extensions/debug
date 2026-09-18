<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\cache;

use function array_key_exists;

/**
 * Application-owned cache service reporting every operation to its collector.
 */
final class Cache
{
    /**
     * @var array<string, mixed> Stored values keyed by cache key.
     */
    private array $values = [];

    /**
     * @param CacheCollector $collector Collector receiving every recorded operation.
     */
    public function __construct(private readonly CacheCollector $collector) {}

    /**
     * Reads a value and records the lookup outcome.
     *
     * @param string $key Cache key to read.
     *
     * @return mixed Stored value, or `null` when the key is absent.
     */
    public function get(string $key): mixed
    {
        $hit = array_key_exists($key, $this->values);

        $this->collector->record('get', $key, $hit ? 'hit' : 'miss');

        return $hit ? $this->values[$key] : null;
    }

    /**
     * Stores a value and records the write.
     *
     * @param string $key Cache key to write.
     * @param mixed $value Value to store.
     */
    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;

        $this->collector->record('set', $key, 'stored');
    }
}
