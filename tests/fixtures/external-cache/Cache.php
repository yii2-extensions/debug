<?php

declare(strict_types=1);

namespace Acme\Debug;

use Psr\Log\LoggerInterface;

use function array_key_exists;

/**
 * A tiny application-owned cache that emits PSR-3 diagnostics, without knowing about the debugger.
 */
final class Cache
{
    /**
     * @var array<string, mixed>
     */
    private array $values = [];

    public function __construct(private readonly LoggerInterface $logger) {}

    public function get(string $key): mixed
    {
        $hit = array_key_exists($key, $this->values);

        $this->logger->debug(
            'Cache {operation}: {result}',
            [
                'event' => 'cache.operation',
                'operation' => 'get',
                'key' => $key,
                'result' => $hit ? 'hit' : 'miss',
            ],
        );

        return $this->values[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;

        $this->logger->debug(
            'Cache {operation}: {result}',
            [
                'event' => 'cache.operation',
                'operation' => 'set',
                'key' => $key,
                'result' => 'stored',
            ],
        );
    }
}
