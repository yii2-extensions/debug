<?php

declare(strict_types=1);

namespace PHPForge\Vite;

use LogicException;
use PHPForge\Vite\Configuration\{DevelopmentConfiguration, ProductionConfiguration};

use function count;

/**
 * Stand-in for the optional framework-neutral Vite facade.
 */
final readonly class Vite
{
    /**
     * @param list<string> $entrypoints
     */
    public function __construct(
        private DevelopmentConfiguration|ProductionConfiguration $configuration,
        private array $entrypoints = [],
    ) {}

    /**
     * Fails when instrumentation attempts to execute asset resolution instead of inspecting Yii configuration.
     */
    public function resolve(): never
    {
        throw new LogicException(
            'The debugger must not resolve ' . $this->configuration::class . ' with ' . count($this->entrypoints)
                . ' entrypoints.',
        );
    }
}
