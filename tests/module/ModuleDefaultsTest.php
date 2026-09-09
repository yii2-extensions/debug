<?php

declare(strict_types=1);

namespace yii\debug\tests\module;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\debug\collectors\TimelineCollector;
use yii\debug\{Module, VersionResolver};
use yii\debug\panels\{RouterPanel, TimelinePanel};
use yii\debug\tests\support\ModuleTestCase;

use function array_keys;

/**
 * Unit tests for {@see Module} covering built-in action/collector IDs and panel order, Router visibility, explicit
 * Timeline registration, view aliases, namespace defaults, package version resolution, and literal/callable page titles.
 */
#[Group('module')]
final class ModuleDefaultsTest extends ModuleTestCase
{
    public function testBuiltInRouterPanelIsHiddenWhileItsCollectorRemainsRegistered(): void
    {
        $module = new Module('debug');

        $corePanels = $this->invoke(
            $module,
            'corePanels',
        );

        $router = $module->panels['router'] ?? self::fail('Built-in Router panel must remain registered.');

        self::assertIsArray(
            $corePanels,
            'Core panel definitions must remain an array.'
        );
        self::assertSame(
            ['class' => RouterPanel::class, 'standalone' => false],
            $corePanels['router'] ?? null,
            'The built-in Router definition must explicitly opt out of standalone presentation.',
        );
        self::assertInstanceOf(
            RouterPanel::class,
            $router,
            'Built-in Router must resolve its standard panel class.'
        );
        self::assertFalse(
            $router->isVisible(),
            'Built-in Router must act as a hidden compatibility data source for Request.',
        );
        self::assertTrue(
            $module->getCollectorCoordinator()->hasCollector('router'),
            'Hiding Router presentation must not disable route-trace collection.',
        );
    }

    public function testCoreActionsAndCollectorsExposeEveryBuiltInId(): void
    {
        $module = new Module('debug');

        $coreActions = $this->invoke(
            $module,
            'coreActionMap',
        );
        $coreCollectors = $this->invoke(
            $module,
            'coreCollectors',
        );

        self::assertIsArray(
            $coreActions,
            'Core action map must be an array.',
        );
        self::assertIsArray(
            $coreCollectors,
            'Core collector map must be an array.',
        );

        self::assertSame(
            [
                'compare',
                'download-mail',
                'index',
                'php-info',
                'reset-identity',
                'set-identity',
                'toolbar-data',
                'view',
            ],
            array_keys($coreActions),
            'Core action map must retain every debugger endpoint.',
        );
        self::assertSame(
            [
                'asset',
                'config',
                'db',
                'dump',
                'event',
                'inertia',
                'vite',
                'log',
                'mail',
                'profiling',
                'queue',
                'request',
                'router',
                'user',
            ],
            array_keys($coreCollectors),
            'Core collector map must retain every adapter.',
        );
    }

    public function testCorePanelsFollowTheNavigationAndToolbarRequestFlowOrder(): void
    {
        $module = new Module('debug');

        $corePanels = $this->invoke(
            $module,
            'corePanels',
        );

        self::assertIsArray(
            $corePanels,
            'Core panel configurations must be an array.',
        );
        self::assertSame(
            [
                'config',
                'request',
                'log',
                'event',
                'profiling',
                'db',
                'router',
                'user',
                'dump',
                'asset',
                'inertia',
                'mail',
                'queue',
                'vite',
            ],
            array_keys($corePanels),
            'Navigation and toolbar order: Request, Logs, Events, Profiling, and Database first, then diagnostics and integrations.',
        );
    }

    public function testDefaultVersionFallsBackToInstalledExtensionVersion(): void
    {
        $module = new Module('debug');

        self::assertSame(
            VersionResolver::forPackage('yii2-extensions/debug') ?? 'unknown',
            $module->getVersion(),
            'Module version must resolve from Composer package metadata.',
        );
    }

    public function testExplicitRouterPanelClassRetainsStandaloneVisibility(): void
    {
        $module = new Module(
            'debug',
            null,
            ['panels' => ['router' => RouterPanel::class]],
        );

        $router = $module->panels['router'] ?? self::fail('Explicit Router panel must be registered.');

        self::assertInstanceOf(
            RouterPanel::class,
            $router,
            'Explicit Router class must resolve normally.'
        );
        self::assertTrue(
            $router->isVisible(),
            'Explicit class configuration must preserve RouterPanel standalone compatibility.',
        );
    }

    public function testHtmlTitleResolvesCallableTitle(): void
    {
        $module = new Module('debug');

        Yii::$app->request->setHostInfo('https://debug.example');
        Yii::$app->request->setBaseUrl('/app');

        $module->pageTitle = static fn(string $base): string => "Title for {$base}";

        self::assertSame(
            'Title for https://debug.example/app',
            $module->htmlTitle(),
            "Callable 'pageTitle' must receive the absolute base URL.",
        );
    }

    public function testHtmlTitleUsesLiteralStringWhenSet(): void
    {
        $module = new Module('debug');

        $module->pageTitle = 'My Debug';

        self::assertSame(
            'My Debug',
            $module->htmlTitle(),
            'Literal page title must be preserved.',
        );
    }

    public function testInitConfiguresSharedViewAlias(): void
    {
        Yii::setAlias(Module::VIEW_PATH_ALIAS, '@runtime/not-debug-core');

        $module = new Module('debug');

        self::assertSame(
            Yii::getAlias($module->viewPath),
            Yii::getAlias(Module::VIEW_PATH_ALIAS),
            'The adapter-owned alias must target the shared Debug Core templates.',
        );
    }

    public function testModuleInitRetainsYiiNamespaceDefaults(): void
    {
        $module = new Module('debug');

        self::assertSame(
            'yii\\debug\\controllers',
            $module->controllerNamespace,
            'Module::controllerNamespace must default to the Yii debug controllers namespace.'
        );
        self::assertSame(
            'yii\\debug\\controllers',
            $module->actionNamespace,
            'Module::actionNamespace must default to the Yii debug controllers namespace.'
        );
    }

    public function testTimelineCanBeConfiguredExplicitly(): void
    {
        $module = new Module(
            'debug',
            null,
            [
                'collectors' => ['timeline' => TimelineCollector::class],
                'panels' => ['timeline' => TimelinePanel::class],
            ],
        );

        self::assertTrue(
            $module->getCollectorCoordinator()->hasCollector('timeline'),
            'Explicit configuration must continue to register the standalone Timeline collector.',
        );
        self::assertInstanceOf(
            TimelinePanel::class,
            $module->panels['timeline'] ?? null,
            'Explicit configuration must continue to register the standalone Timeline panel.',
        );
    }
}
