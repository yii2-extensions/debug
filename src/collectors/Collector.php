<?php

declare(strict_types=1);

namespace yii\debug\collectors;

use Closure;
use PHPForge\Debug\Collector\CollectorInterface;
use Stringable;
use yii\base\InvalidConfigException;
use yii\debug\exception\Message;
use yii\debug\{LogTarget, Module};
use yii\helpers\VarDumper;

use function is_string;

/**
 * Base class for the Yii2 debug collectors.
 *
 * Owns the idempotent startup/shutdown lifecycle and the debug-module context; subclasses hook event subscription
 * into {@see start()} / {@see stop()} and read accumulated log messages through {@see getLogMessages()}.
 *
 * @phpstan-import-type LogMessage from \PHPForge\Debug\Panel\Log\LogSnapshot
 */
abstract class Collector implements CollectorInterface
{
    /**
     * Debug module supplying the log target and sibling collectors, assigned by the module on registration.
     */
    public Module|null $module = null;

    private bool $started = false;

    /**
     * Deactivates the collector once, releasing any subscription created by {@see start()}.
     *
     * Usage example:
     *
     * ```php
     * $collector->shutdown();
     * ```
     */
    final public function shutdown(): void
    {
        if (!$this->started) {
            return;
        }

        $this->started = false;

        $this->stop();
    }

    /**
     * Activates the collector once at the start of the request lifecycle.
     *
     * Usage example:
     *
     * ```php
     * $collector->startup();
     * ```
     */
    final public function startup(): void
    {
        if ($this->started) {
            return;
        }

        $this->started = true;

        $this->start();
    }

    /**
     * Returns the accumulated log messages, optionally filtered by level, category, and exclusion lists.
     *
     * @param int $levels Bitmask of `yii\log\Logger::LEVEL_*` values; `0` keeps every level.
     * @param list<string> $categories Category patterns to include; empty keeps every category.
     * @param list<string> $except Category patterns to exclude.
     * @param Closure(mixed): string|null $formatter Custom payload formatter; defaults to a readable string export.
     *
     * @throws InvalidConfigException When the debug module log target is not initialized.
     *
     * @return list<LogMessage> Canonical string-based log messages in capture order.
     */
    protected function getLogMessages(
        int $levels = 0,
        array $categories = [],
        array $except = [],
        Closure|null $formatter = null,
    ): array {
        $target = $this->getLogTarget();

        $filteredMessages = LogTarget::filterMessages($target->messages, $levels, $categories, $except);
        $messages = [];
        $formatter ??= self::formatLogMessage(...);

        foreach ($filteredMessages as $message) {
            $messages[] = [
                $formatter($message[0]),
                $message[1],
                $message[2],
                $message[3],
                $message[4],
                $message[5] ?? 0,
            ];
        }

        return $messages;
    }

    /**
     * Returns the initialized log target of the owning debug module.
     *
     * @throws InvalidConfigException When the debug module log target is not initialized.
     *
     * @return LogTarget Initialized log target.
     */
    protected function getLogTarget(): LogTarget
    {
        $logTarget = $this->module?->logTarget;

        if (!$logTarget instanceof LogTarget) {
            throw new InvalidConfigException(
                Message::LOG_TARGET_NOT_INITIALIZED_FOR_READING->getMessage(),
            );
        }

        return $logTarget;
    }

    /**
     * Returns whether the collector is active for the current request.
     */
    protected function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * Hook invoked once when the collector activates; subclasses subscribe to framework events here.
     */
    protected function start(): void {}

    /**
     * Hook invoked once when the collector deactivates; subclasses detach subscriptions and clear state here.
     */
    protected function stop(): void {}

    /**
     * Converts a Yii log payload to the string consumed by shared snapshots and HTML renderers.
     */
    private static function formatLogMessage(mixed $message): string
    {
        if (is_string($message)) {
            return $message;
        }

        return $message instanceof Stringable ? (string) $message : VarDumper::export($message);
    }
}
