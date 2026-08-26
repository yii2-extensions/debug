<?php

declare(strict_types=1);

namespace PHPForge\Vite\Configuration;

/**
 * Stand-in for the optional framework-neutral Vite production configuration.
 */
final readonly class ProductionConfiguration
{
    public function __construct(
        public string $manifestPath,
        public string $assetBaseUrl,
        public bool $modulePreload = true,
    ) {}
}
