<?php

declare(strict_types=1);

namespace yii\debug\tests\support;

use Yii;
use yii\log\Dispatcher;

/**
 * Shared web-application and asset-directory setup for module tests, with optional logger suppression.
 */
abstract class ModuleTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $assetBasePath = dirname(__DIR__, 2) . '/runtime/assets';

        if (!is_dir($assetBasePath) && !mkdir($assetBasePath, 0o755, true) && !is_dir($assetBasePath)) {
            self::fail(
                "Could not create asset base path: {$assetBasePath}",
            );
        }

        $this->mockWebApplication(
            [
                'components' => [
                    'assetManager' => [
                        'basePath' => $assetBasePath,
                        'baseUrl' => '/assets',
                    ],
                ],
            ],
        );
    }

    /**
     * Replaces the default log dispatcher with a no-op so toolbar rendering does not flush events.
     */
    protected function silenceLogger(): void
    {
        Yii::getLogger()->dispatcher = self::createStub(Dispatcher::class);
    }
}
