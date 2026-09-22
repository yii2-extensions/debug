<?php

declare(strict_types=1);

namespace yii\debug\service;

use PHPForge\Debug\Registration\PanelRegistry;
use yii\debug\Panel;

/**
 * Carries the panels the debugger registered and the catalog describing their display order.
 */
final readonly class PanelCatalog
{
    /**
     * @param array<string, Panel> $panels Enabled panels indexed by ID in display order.
     * @param PanelRegistry $registry Effective catalog carrying the display order and the IDs disabled by
     * configuration.
     */
    public function __construct(public array $panels, public PanelRegistry $registry) {}
}
