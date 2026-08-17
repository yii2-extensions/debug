<?php

declare(strict_types=1);

namespace yii\debug\panels;

use PHPForge\Debug\Panel\MemorySample;

/**
 * Contract for panels that feed the timeline memory chart.
 *
 * {@see \yii\debug\models\timeline\Svg::$listenMessages} lists the panel ids to read, so any panel implementing this
 * interface can contribute samples — the chart is not limited to the core Log and Profiling panels.
 */
interface ProvidesMemorySamples
{
    /**
     * @return list<MemorySample> Samples in capture order.
     */
    public function getMemorySamples(): array;
}
