<?php

declare(strict_types=1);

namespace PHPForge\Vite\Configuration;

/**
 * Stand-in for the optional framework-neutral Vite development configuration.
 */
final readonly class DevelopmentConfiguration
{
    /**
     * @param list<object> $inlineModuleProviders
     */
    public function __construct(
        public string $devServerUrl,
        public bool $includeViteClient = true,
        public array $inlineModuleProviders = [],
    ) {}
}
