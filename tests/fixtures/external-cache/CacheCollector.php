<?php

declare(strict_types=1);

namespace Acme\Debug;

use PHPForge\Debug\CollectorInterface;
use Psr\Log\{AbstractLogger, LoggerInterface};
use Stringable;

/**
 * Decorates the application's logger while collecting cache diagnostics only during an active request.
 */
final class CacheCollector extends AbstractLogger implements CollectorInterface
{
    /**
     * @var list<array{mixed, mixed, mixed}>
     */
    private array $operations = [];
    private bool $started = false;

    public function __construct(private readonly string $panelId, private readonly LoggerInterface $logger) {}

    /**
     * @return array{schema: int, operations: list<array{mixed, mixed, mixed}>}|null
     */
    public function capture(): array|null
    {
        return $this->started ? ['schema' => 1, 'operations' => $this->operations] : null;
    }

    public function id(): string
    {
        return $this->panelId;
    }

    /**
     * @param mixed $level
     * @param array<array-key, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);

        if ($this->started && ($context['event'] ?? null) === 'cache.operation') {
            $this->operations[] = [
                $context['operation'] ?? null,
                $context['key'] ?? null,
                $context['result'] ?? null];
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
