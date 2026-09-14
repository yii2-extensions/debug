<?php

declare(strict_types=1);

namespace yii\debug\panels;

use PHPForge\Debug\Storage\RequestSummary;

/**
 * Receives the loaded request summary before the active panel renders.
 */
interface RequestSummaryAwarePanelInterface
{
    /**
     * Provides the immutable summary for the request currently being rendered.
     *
     * @param RequestSummary $summary Summary loaded for the capture under inspection.
     */
    public function setRequestSummary(RequestSummary $summary): void;
}
