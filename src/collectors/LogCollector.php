<?php

declare(strict_types=1);

namespace yii\debug\collectors;

use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Panel\Log\LogSnapshot;
use yii\log\Logger;

/**
 * Captures error, warning, info, and trace log messages emitted during the request for the Logs panel.
 *
 * Skips categories owned by the Router collector to avoid duplicate rows in the routing trace.
 *
 * Usage example:
 *
 * ```php
 * $snapshot = (new \yii\debug\collectors\LogCollector())->capture();
 * ```
 */
class LogCollector extends Collector
{
    /**
     * Captures every error/warning/info/trace log message, excluding the categories owned by the Router collector.
     *
     * @return LogSnapshot|null Captured log payload; `null` when the collector never started.
     */
    public function capture(): LogSnapshot|null
    {
        if (!$this->isStarted()) {
            return null;
        }

        $except = [];

        $routerCollector = $this->module?->getCollectorCoordinator()->collector('router');

        if ($routerCollector instanceof RouterCollector) {
            $except = Coerce::stringList($routerCollector->getCategories());
        }

        $messages = $this->getLogMessages(
            Logger::LEVEL_ERROR | Logger::LEVEL_INFO | Logger::LEVEL_WARNING | Logger::LEVEL_TRACE,
            [],
            $except,
            true,
        );

        return LogSnapshot::capture($messages);
    }

    /**
     * Returns the stable ID pairing this collector with the Logs panel.
     *
     * Usage example:
     *
     * ```php
     * $id = (new \yii\debug\collectors\LogCollector())->id();
     * ```
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'log';
    }
}
