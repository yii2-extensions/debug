<?php

declare(strict_types=1);

namespace yii\debug\collectors;

use PHPForge\Debug\Collector\CollectorInterface;
use Throwable;
use yii\base\InvalidConfigException;
use yii\debug\{LogTarget, Module};
use yii\helpers\VarDumper;

use function array_key_exists;
use function is_array;
use function is_string;

/**
 * Base class for the Yii2 debug collectors.
 *
 * Owns the idempotent startup/shutdown lifecycle and the debug-module context; subclasses hook event subscription
 * into {@see start()} / {@see stop()} and read accumulated log messages through {@see getLogMessages()}.
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
     * @param array<int, string> $categories Category patterns to include; empty keeps every category.
     * @param array<int, string> $except Category patterns to exclude.
     * @param bool $stringify Whether to export non-string message payloads as parsable strings.
     *
     * @throws InvalidConfigException When the debug module log target is not initialized.
     *
     * @return array<int, array<int|string, mixed>> Filtered log messages in capture order.
     */
    protected function getLogMessages(
        int $levels = 0,
        array $categories = [],
        array $except = [],
        bool $stringify = false,
    ): array {
        $target = $this->getLogTarget();

        $filteredMessages = LogTarget::filterMessages($target->messages, $levels, $categories, $except);

        $messages = [];

        foreach ($filteredMessages as $message) {
            if (is_array($message)) {
                $messages[] = $message;
            }
        }

        if (!$stringify) {
            return $messages;
        }

        foreach ($messages as $key => $message) {
            if (!array_key_exists(0, $message) || is_string($message[0])) {
                continue;
            }

            if ($message[0] instanceof Throwable) {
                $messages[$key][0] = (string) $message[0];
            } else {
                $messages[$key][0] = VarDumper::export($message[0]);
            }
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
                'The debug module logTarget must be initialized before reading log messages.',
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
}
