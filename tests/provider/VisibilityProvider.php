<?php

declare(strict_types=1);

namespace yii\debug\tests\provider;

use yii\debug\actions\{
    Action,
    DownloadMailAction,
    IndexAction,
    PhpInfoAction,
    ViewAction,
};
use yii\debug\collectors\{Collector, ConfigCollector, DbCollector, RequestCollector, UserCollector};
use yii\debug\db\DebugPdoStatement;
use yii\debug\{LogTarget, Module};
use yii\debug\models\router\{ActionRoutes, RouterRules};
use yii\debug\models\timeline\Svg;
use yii\debug\panels\{AssetPanel, DbPanel, DumpPanel, LogPanel, ProfilingPanel};

/**
 * Data provider for extension method contract test cases.
 *
 * Provides declared public and protected method contracts for extension points.
 */
final class VisibilityProvider
{
    /**
     * @return iterable<string, array{0: class-string, 1: string, 2: 'protected'|'public'}>
     */
    public static function actionContracts(): iterable
    {
        yield from self::cases(Action::class, 'public', ['getDebugModule', 'prepareShell', 'render']);
        yield from self::cases(
            Action::class,
            'protected',
            ['createBareShellContext', 'createShellContext', 'getLogTarget', 'resolveTheme'],
        );
        yield from self::cases(DownloadMailAction::class, 'public', ['run']);
        yield from self::cases(IndexAction::class, 'public', ['run']);
        yield from self::cases(PhpInfoAction::class, 'public', ['run']);
        yield from self::cases(ViewAction::class, 'public', ['run']);
    }

    /**
     * @return iterable<string, array{0: class-string, 1: string, 2: 'protected'|'public'}>
     */
    public static function actionRoutesContracts(): iterable
    {
        yield from self::cases(
            ActionRoutes::class,
            'protected',
            ['getActions', 'getAppRoutes', 'getMatchedCreationRule', 'getModuleControllers', 'validateControllerClass'],
        );
    }

    /**
     * @return iterable<string, array{0: class-string, 1: string, 2: 'protected'|'public'}>
     */
    public static function assetPanelContracts(): iterable
    {
        yield from self::cases(AssetPanel::class, 'public', ['getBundles']);
    }

    /**
     * @return iterable<string, array{0: class-string, 1: string, 2: 'protected'|'public'}>
     */
    public static function collectorContracts(): iterable
    {
        yield from self::cases(
            Collector::class,
            'protected',
            ['getLogMessages', 'getLogTarget', 'start', 'stop'],
        );
    }

    /**
     * @return iterable<string, array{0: class-string, 1: string, 2: 'protected'|'public'}>
     */
    public static function configCollectorContracts(): iterable
    {
        yield from self::cases(ConfigCollector::class, 'protected', ['getApplication']);
    }

    /**
     * @return iterable<string, array{0: class-string, 1: string, 2: 'protected'|'public'}>
     */
    public static function dbCollectorContracts(): iterable
    {
        yield from self::cases(DbCollector::class, 'protected', ['getQueryType']);
    }

    /**
     * @return iterable<string, array{0: class-string, 1: string, 2: 'protected'|'public'}>
     */
    public static function dbPanelContracts(): iterable
    {
        yield from self::cases(DbPanel::class, 'public', ['countCallerCals']);
        yield from self::cases(
            DbPanel::class,
            'protected',
            ['getModels', 'getTotalQueryTime', 'hasExplain'],
        );
    }

    /**
     * @return iterable<string, array{0: class-string, 1: string, 2: 'protected'|'public'}>
     */
    public static function debugPdoStatementContracts(): iterable
    {
        yield from self::cases(DebugPdoStatement::class, 'protected', ['__construct']);
    }

    /**
     * @return iterable<string, array{0: class-string, 1: string, 2: 'protected'|'public'}>
     */
    public static function dumpPanelContracts(): iterable
    {
        yield from self::cases(DumpPanel::class, 'protected', ['getModels']);
    }

    /**
     * @return iterable<string, array{0: class-string, 1: string, 2: 'protected'|'public'}>
     */
    public static function logPanelContracts(): iterable
    {
        yield from self::cases(LogPanel::class, 'protected', ['getModels']);
    }

    /**
     * @return iterable<string, array{0: class-string, 1: string, 2: 'protected'|'public'}>
     */
    public static function logTargetContracts(): iterable
    {
        yield from self::cases(
            LogTarget::class,
            'protected',
            ['collectSummary', 'getExcessiveDbCallersCount', 'getSqlTotalCount'],
        );
    }

    /**
     * @return iterable<string, array{0: class-string, 1: string, 2: 'protected'|'public'}>
     */
    public static function moduleContracts(): iterable
    {
        yield from self::cases(
            Module::class,
            'protected',
            [
                'checkAccess',
                'coreActionMap',
                'coreCollectors',
                'corePanels',
                'initActionMap',
                'initCollectors',
                'initPanels',
                'initPanelServices',
                'resetGlobalSettings',
            ],
        );
    }

    /**
     * @return iterable<string, array{0: class-string, 1: string, 2: 'protected'|'public'}>
     */
    public static function profilingPanelContracts(): iterable
    {
        yield from self::cases(ProfilingPanel::class, 'public', ['getMemoryUsage']);
    }

    /**
     * @return iterable<string, array{0: class-string, 1: string, 2: 'protected'|'public'}>
     */
    public static function requestCollectorContracts(): iterable
    {
        yield from self::cases(
            RequestCollector::class,
            'protected',
            ['censorArray', 'getFlashes', 'normalizeResponseHeaders'],
        );
    }

    /**
     * @return iterable<string, array{0: class-string, 1: string, 2: 'protected'|'public'}>
     */
    public static function routerRulesContracts(): iterable
    {
        yield from self::cases(
            RouterRules::class,
            'protected',
            ['scanGroupRule', 'scanRestRule', 'scanRule'],
        );
    }

    /**
     * @return iterable<string, array{0: class-string, 1: string, 2: 'protected'|'public'}>
     */
    public static function svgContracts(): iterable
    {
        yield from self::cases(Svg::class, 'protected', ['addPoints']);
    }

    /**
     * @return iterable<string, array{0: class-string, 1: string, 2: 'protected'|'public'}>
     */
    public static function userCollectorContracts(): iterable
    {
        yield from self::cases(
            UserCollector::class,
            'protected',
            ['dataToString', 'getUser', 'identityData'],
        );
    }

    /**
     * @param class-string $class
     * @param 'protected'|'public' $visibility
     * @param list<string> $methods
     *
     * @return iterable<string, array{0: class-string, 1: string, 2: 'protected'|'public'}>
     */
    private static function cases(string $class, string $visibility, array $methods): iterable
    {
        foreach ($methods as $method) {
            yield "{$class}::{$method} is {$visibility}" => [$class, $method, $visibility];
        }
    }
}
