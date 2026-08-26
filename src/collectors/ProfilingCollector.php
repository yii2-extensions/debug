<?php

declare(strict_types=1);

namespace yii\debug\collectors;

use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Panel\Profile\ProfilingSnapshot;
use yii\log\Logger;

use function memory_get_peak_usage;
use function microtime;

/**
 * Captures profile-level log messages emitted by `Yii::beginProfile()` for the Profiling panel.
 */
class ProfilingCollector extends Collector
{
    /**
     * Snapshots the captured profile messages, the peak memory usage, and the total request time.
     *
     * @return ProfilingSnapshot|null Captured profiling payload; `null` when the collector never started.
     */
    public function capture(): ProfilingSnapshot|null
    {
        if (!$this->isStarted()) {
            return null;
        }

        $messages = $this->getLogMessages(Logger::LEVEL_PROFILE);

        $requestStart = Coerce::floatOrNull($_SERVER['REQUEST_TIME_FLOAT'] ?? null) ?? microtime(true);

        return ProfilingSnapshot::capture(
            memory_get_peak_usage(),
            microtime(true) - $requestStart,
            $messages,
        );
    }

    /**
     * Returns the stable ID pairing this collector with the Profiling panel.
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'profiling';
    }
}
