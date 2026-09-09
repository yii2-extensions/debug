<?php

declare(strict_types=1);

namespace yii\debug\tests\module;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\debug\actions\{PhpInfoAction, ViewAction};
use yii\debug\Module;
use yii\debug\tests\support\ModuleTestCase;
use yii\debug\tests\support\stub\ConfigurableAction;
use yii\web\AssetManager;

/**
 * Unit tests for {@see Module} covering standalone `php-info` execution and `createStandaloneAction` resolution
 * of default, configured, slash-delimited, and nested routes.
 */
#[Group('module')]
final class ModuleActionTest extends ModuleTestCase
{
    public function testActionPhpInfoIsCallableStandalone(): void
    {
        $module = new Module('debug');

        $module->allowedIPs = ['*'];

        $app = Yii::$app;

        $app->setModule(
            'debug',
            $module,
        );
        $module->bootstrap($app);

        $assetBasePath = Yii::getAlias('@runtime/assets');

        if (!is_dir($assetBasePath) && !mkdir($assetBasePath, 0o755, true) && !is_dir($assetBasePath)) {
            self::fail(
                "Could not create asset base path: {$assetBasePath}",
            );
        }

        $app->set(
            'assetManager',
            [
                'class' => AssetManager::class,
                'basePath' => $assetBasePath,
                'baseUrl' => '/assets',
            ],
        );

        $action = new PhpInfoAction('php-info');

        $action->setModule($module);

        $output = $action->runWithParams([]);

        self::assertIsString(
            $output,
            'Rendered output must be a string.',
        );
        self::assertStringContainsString(
            'phpinfo',
            $output,
            "'phpinfo' view must include the heading literal.",
        );
    }

    public function testCreateStandaloneActionDoesNotTreatNestedMapKeysAsDirectActions(): void
    {
        $module = new Module('debug');

        $module->actionMap['nested/action'] = PhpInfoAction::class;

        self::assertNull(
            $this->invoke($module, 'createStandaloneAction', ['nested/action']),
            'Nested routes must fall through instead of being resolved as direct action IDs.',
        );
    }

    public function testCreateStandaloneActionResolvesEmptyRouteThroughDefaultActionMap(): void
    {
        $module = new Module('debug');

        $action = $this->invoke(
            $module,
            'createStandaloneAction',
            [''],
        );

        self::assertInstanceOf(
            \yii\debug\actions\IndexAction::class,
            $action,
            'The module root must resolve the configured default route through the standalone action map.',
        );
        self::assertSame(
            'index',
            $action->id,
            'The action resolved for the module root must retain the canonical index ID.',
        );
    }

    public function testCreateStandaloneActionSupportsYiiDoubleUnderscoreClassConfiguration(): void
    {
        $module = new Module('debug');

        $module->actionMap['configured'] = [
            '__class' => ConfigurableAction::class,
            'label' => 'resolved',
        ];

        $action = $this->invoke(
            $module,
            'createStandaloneAction',
            ['configured'],
        );

        self::assertInstanceOf(
            ConfigurableAction::class,
            $action,
            "Yii '__class__' configuration must resolve.",
        );
        self::assertSame(
            'configured',
            $action->id,
            'The resolved action ID must be assigned.'
        );
        self::assertSame(
            'resolved',
            $action->label,
            'Configured action properties must be preserved.'
        );
        self::assertSame(
            $module,
            $action->getModule(),
            'The resolved action must be bound to its module.'
        );
    }

    public function testCreateStandaloneActionTrimsSurroundingSlashesFromMappedRoutes(): void
    {
        $module = new Module('debug');

        $action = $this->invoke(
            $module,
            'createStandaloneAction',
            ['/view/'],
        );

        self::assertInstanceOf(
            ViewAction::class,
            $action,
            'Mapped action must resolve despite surrounding slashes.'
        );
        self::assertSame(
            'view',
            $action->id,
            'Resolved ID must drop the surrounding slashes.'
        );
    }
}
