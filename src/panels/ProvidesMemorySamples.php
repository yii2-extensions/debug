<?php

declare(strict_types=1);

namespace yii\debug\panels;

use PHPForge\Debug\Panel\MemorySample;

/**
 * Contract for panels that feed the Profiling timeline memory graph.
 *
 * {@see ProfilingPanel} plots its own samples and appends those of the panel registered under the `log` id when that
 * panel implements this interface, so a replacement Log panel keeps contributing readings to the graph.
 */
interface ProvidesMemorySamples
{
    /**
     * Returns the memory readings this panel contributes to the Timeline graph.
     *
     * @return list<MemorySample> Samples in capture order.
     */
    public function getMemorySamples(): array;
}
