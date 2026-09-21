<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\vite;

use PHPForge\Vite\Configuration\DevelopmentConfiguration;

/**
 * Stand-in for a `vite` component whose constructor declares no `eventDispatcher` parameter.
 */
final readonly class ViteWithoutDispatcher
{
    public function __construct(public DevelopmentConfiguration $configuration) {}
}
