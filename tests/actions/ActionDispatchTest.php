<?php

declare(strict_types=1);

namespace yii\debug\tests\actions;

use PHPForge\Debug\Panel\Config\ConfigSnapshot;
use PHPForge\Debug\Panel\Db\DbSnapshot;
use PHPForge\Debug\Panel\Db\QueryRow;
use PHPUnit\Framework\Attributes\Group;
use Xepozz\InternalMocker\MockerState;
use Yii;
use yii\debug\actions\IndexAction;
use yii\debug\Module;
use yii\debug\tests\support\ActionTestCase;
use yii\debug\tests\support\stub\ConfigurableAction;

/**
 * Unit tests for {@see Module} covering available panel-action registration, application-level and module-level
 * `runAction` dispatch, bare module routes, configured action properties, and database panel injection.
 */
#[Group('actions')]
final class ActionDispatchTest extends ActionTestCase
{
    public function testActionMapAdoptsOnlyAvailablePanelActions(): void
    {
        MockerState::addCondition(
            'yii\debug',
            'class_exists',
            ['yii\queue\Queue'],
            false,
        );

        $module = $this->bootDebugModule();

        self::assertArrayHasKey(
            'compare',
            $module->actionMap,
            "'compare' must be registered as a built-in standalone action.",
        );
        self::assertArrayHasKey(
            'index',
            $module->actionMap,
            "'index' must be registered as a built-in standalone action.",
        );
        self::assertArrayHasKey(
            'db-explain',
            $module->actionMap,
            "'db-explain' action must be adopted from the DbPanel.",
        );
        self::assertArrayNotHasKey(
            'queue-job',
            $module->actionMap,
            "'queue-job' must stay unregistered when the Queue extension is unavailable.",
        );
    }

    public function testAppDispatchesBareDebugModuleRouteThroughIndexActionMap(): void
    {
        $module = $this->bootDebugModule();

        $this->writeDebugSnapshot(
            $module,
            'tag-bare-module-route',
            [
                'config' => ConfigSnapshot::capture(
                    [
                        'application' => ['yii' => 'bare-route-yii'],
                        'php' => ['version' => 'bare-route-php'],
                    ],
                ),
            ],
        );

        Yii::$app->requestedRoute = $module->getUniqueId();

        $html = Yii::$app->runAction($module->getUniqueId());

        self::assertIsString(
            $html,
            'The bare module route must return the rendered request history.',
        );
        self::assertStringContainsString(
            'Request history',
            $html,
            "Route 'debug' must resolve to the same standalone index action as 'debug/index'.",
        );
        self::assertInstanceOf(
            IndexAction::class,
            Yii::$app->requestedAction,
            'Bare module dispatch must expose the canonical index action to the Yii lifecycle.',
        );
    }

    public function testAppDispatchesBuiltInDebugRouteThroughActionMap(): void
    {
        $module = $this->bootDebugModule();

        // Application-level dispatch of a module-prefixed route reaches the module through 'createStandaloneAction()',
        // which the module overrides to resolve the endpoint from its action map (the URL manager path).
        $html = Yii::$app->runAction("{$module->getUniqueId()}/php-info");

        self::assertIsString(
            $html,
            'Module-prefixed dispatch must return the rendered output.',
        );
        self::assertStringContainsString(
            'phpinfo',
            $html,
            "Route 'debug/php-info' must resolve from the action map.",
        );
    }

    public function testAppDispatchesPanelDebugRouteThroughActionMap(): void
    {
        $module = $this->bootDebugModule();

        $this->writeDebugSnapshot(
            $module,
            'tag-panel-route',
            ['db' => new DbSnapshot([self::queryRow('SELECT 1')])],
        );

        Yii::$app->getRequest()->setUrl('dummy');

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        // The 'db-explain' id maps to the sub-namespaced 'actions\db\ExplainAction', which convention discovery
        // cannot derive; the action-map lookup keeps the URL reachable.
        try {
            $html = Yii::$app->runAction(
                "{$module->getUniqueId()}/db-explain",
                [
                    'seq' => '0',
                    'tag' => 'tag-panel-route',
                ],
            );
        } finally {
            unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        }

        self::assertIsString(
            $html,
            'Module-prefixed panel dispatch must return the rendered output.',
        );
        self::assertStringContainsString(
            '<span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>',
            $html,
            "Sub-namespaced 'db-explain' action must resolve from the action map.",
        );
    }

    public function testAppDispatchPreservesConfiguredActionProperties(): void
    {
        $module = $this->bootDebugModule();

        // A custom array-shaped entry must keep its configured properties, matching direct 'runMappedAction' dispatch.
        $module->actionMap['configurable'] = [
            'class' => ConfigurableAction::class,
            'label' => 'configured',
        ];

        $result = Yii::$app->runAction("{$module->getUniqueId()}/configurable");

        self::assertSame(
            'configured',
            $result,
            'Array-shaped action config must apply configured properties.',
        );
    }

    public function testRunActionDispatchesDbExplainThroughActionMapWithInjectedPanel(): void
    {
        $module = $this->bootDebugModule();

        $this->writeDebugSnapshot(
            $module,
            'tag-di',
            ['db' => new DbSnapshot([self::queryRow('SELECT 1')])],
        );

        Yii::$app->getRequest()->setUrl('dummy');

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        try {
            $html = $module->runAction('db-explain', ['seq' => '0', 'tag' => 'tag-di']);
        } finally {
            unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        }

        self::assertIsString(
            $html,
            'Action-map dispatch must return the rendered output.',
        );
        self::assertStringContainsString(
            '<span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>',
            $html,
            'Injected panel must serve the captured query through dispatch.',
        );
    }

    public function testRunActionDispatchesPhpInfoThroughActionMap(): void
    {
        $module = $this->bootDebugModule();

        $html = $module->runAction('php-info');

        self::assertIsString(
            $html,
            'Action-map dispatch must return the rendered output.',
        );
        self::assertStringContainsString(
            'phpinfo',
            $html,
            "Route 'php-info' must resolve to the phpinfo action.",
        );
    }

    private static function queryRow(string $query): QueryRow
    {
        return QueryRow::create($query, 50.0, 1_700_000_000_000.0)
            ->withType('SELECT')
            ->withTraceHash('hash');
    }
}
