<?php

declare(strict_types=1);

namespace yii\debug\collectors;

use PHPForge\Debug\Panel\Log\LogSnapshot;
use yii\log\Logger;

/**
 * Captures error, warning, info, and trace log messages emitted during the request for the Logs panel.
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
            $except = $routerCollector->getCategories();
        }

        $messages = $this->getLogMessages(
            Logger::LEVEL_ERROR | Logger::LEVEL_INFO | Logger::LEVEL_WARNING | Logger::LEVEL_TRACE,
            [],
            $except,
        );

        return LogSnapshot::capture($messages);
    }

    /**
     * Returns the stable ID pairing this collector with the Logs panel.
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'log';
    }
}
