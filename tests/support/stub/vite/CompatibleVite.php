<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\vite;

use PHPForge\Vite\Configuration\DevelopmentConfiguration;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Stand-in for a `vite` component the Vite provider attaches to, declaring its dispatcher at its own position.
 *
 * The packaged `PHPForge\Vite\Vite` facade is `final`, so a test registers the class relationship on the internal
 * mocker instead of extending it.
 */
final readonly class CompatibleVite
{
    public function __construct(
        public DevelopmentConfiguration $configuration,
        public EventDispatcherInterface|null $eventDispatcher = null,
    ) {}
}
