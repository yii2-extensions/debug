<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use PHPForge\Debug\Panel\Router\RouterSnapshot;
use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\base\{Action, InlineAction};
use yii\debug\collectors\RouterCollector;
use yii\debug\{LogTarget, Module};
use yii\debug\tests\support\TestCase;
use yii\log\Logger;
use yii\web\Controller;

/**
 * Unit tests for {@see RouterCollector} covering the routing trace capture, the action / route narrowing, the
 * category-list extension, and the startup/shutdown lifecycle.
 */
#[Group('collector')]
#[Group('router')]
final class RouterCollectorTest extends TestCase
{
    public function testCaptureBuildsActionFromInlineAction(): void
    {
        $collector = $this->makeCollector();

        $controller = new Controller('site', Yii::$app);
        $action = new InlineAction('index', $controller, 'actionIndex');

        Yii::$app->requestedAction = $action;

        $snapshot = $this->captureSnapshot($collector);

        self::assertSame(
            $controller::class . '::actionIndex()',
            $snapshot->action,
            'Inline actions must use controller-method notation.',
        );
        self::assertSame(
            'site/index',
            $snapshot->route,
            'Route must echo the action unique id.',
        );
    }

    public function testCaptureBuildsActionFromRegularAction(): void
    {
        $collector = $this->makeCollector();

        $controller = new Controller('site', Yii::$app);

        $action = new class ('run', $controller) extends Action {
            public function run(): void {}
        };

        Yii::$app->requestedAction = $action;

        $snapshot = $this->captureSnapshot($collector);

        self::assertSame(
            $action::class . '::run()',
            $snapshot->action,
            'Standalone actions must use class-run notation.',
        );
    }

    public function testCaptureCapturesFilteredLogMessages(): void
    {
        $collector = $this->makeCollector();

        $module = $collector->module ?? self::fail('Module must be wired.');

        $logTarget = $module->logTarget;

        self::assertInstanceOf(
            LogTarget::class,
            $logTarget,
            'Log target must be wired.',
        );

        $logTarget->messages = [
            ['matched', Logger::LEVEL_TRACE, 'yii\\web\\UrlManager::parseRequest', 0.0, [], 0],
            ['dropped', Logger::LEVEL_TRACE, 'application', 0.0, [], 0],
            ['matched-rule', Logger::LEVEL_TRACE, 'yii\\web\\UrlRule::parseRequest', 0.0, [], 0],
        ];

        Yii::$app->requestedRoute = 'site/index';

        $snapshot = $this->captureSnapshot($collector);

        self::assertSame(
            'matched-rule',
            $snapshot->message,
            'Only categories declared in $categories must survive; the last one wins.',
        );
        self::assertSame(
            [],
            $snapshot->entries(),
            'Plain string traces carry no rule rows.',
        );
    }

    public function testCaptureLeavesActionAsNullWhenNoRequestedAction(): void
    {
        $collector = $this->makeCollector();

        Yii::$app->requestedAction = null;
        Yii::$app->requestedRoute = 'site/default';

        $snapshot = $this->captureSnapshot($collector);

        self::assertNull(
            $snapshot->action,
            "Missing requested action must yield 'null'.",
        );
        self::assertSame(
            'site/default',
            $snapshot->route,
            "Route must fall back to 'requestedRoute'.",
        );
    }

    public function testCapturePreservesStructuredRuleTracePayloads(): void
    {
        $collector = $this->makeCollector();

        $module = $collector->module ?? self::fail('Module must be wired.');
        $logTarget = $module->logTarget;

        self::assertInstanceOf(LogTarget::class, $logTarget, 'Log target must be wired.');

        $logTarget->messages = [
            [
                ['rule' => 'site/<action>', 'match' => true, 'parent' => ''],
                Logger::LEVEL_TRACE,
                'yii\\web\\UrlRule::parseRequest',
                0.0,
                [],
                0,
            ],
        ];

        Yii::$app->requestedRoute = 'site/index';

        $snapshot = $this->captureSnapshot($collector);
        $row = $snapshot->entries()[0] ?? self::fail('Expected the structured rule probe to survive capture.');

        self::assertSame('site/<action>', $row->rule, 'Structured rule name must remain inspectable.');
        self::assertTrue($row->match, 'Structured match result must remain inspectable.');
    }

    public function testCaptureReturnsNullAfterShutdown(): void
    {
        $collector = $this->makeCollector();

        $collector->shutdown();

        self::assertNull(
            $collector->capture(),
            'Stopped collector must record nothing.',
        );
    }

    public function testCaptureReturnsNullBeforeStartup(): void
    {
        $this->mockWebApplication();

        self::assertNull(
            (new RouterCollector())->capture(),
            'Idle collector must record nothing.',
        );
    }

    public function testGetCategoriesReturnsDefaultCategories(): void
    {
        $collector = $this->makeCollector();

        self::assertSame(
            [
                'yii\rest\UrlRule::parseRequest',
                'yii\web\CompositeUrlRule::parseRequest',
                'yii\web\UrlManager::parseRequest',
                'yii\web\UrlRule::parseRequest',
            ],
            $collector->getCategories(),
            'Default categories must match the URL manager rule probes.',
        );
    }

    public function testIdPairsWithTheRouterPanel(): void
    {
        self::assertSame(
            'router',
            (new RouterCollector())->id(),
            "Stable ID must be 'router'.",
        );
    }

    public function testSetCategoriesAppendsArrayValues(): void
    {
        $collector = $this->makeCollector();

        $collector->setCategories(['custom\\Probe::parseRequest', 'another\\Probe::parseRequest']);

        $categories = $collector->getCategories();

        self::assertContains(
            'custom\\Probe::parseRequest',
            $categories,
            'First appended entry must be present.',
        );
        self::assertContains(
            'another\\Probe::parseRequest',
            $categories,
            'Second appended entry must be present.',
        );
        self::assertContains(
            'yii\\web\\UrlManager::parseRequest',
            $categories,
            'Defaults must be preserved.',
        );
    }

    public function testSetCategoriesAppendsSingleStringValue(): void
    {
        $collector = $this->makeCollector();

        $collector->setCategories('custom\\Probe::parseRequest');

        self::assertContains(
            'custom\\Probe::parseRequest',
            $collector->getCategories(),
            'Single string must be appended to the category list.',
        );
    }

    /**
     * Captures the routing snapshot, failing when the started collector produces nothing.
     *
     * @param RouterCollector $collector Started collector.
     *
     * @return RouterSnapshot Captured routing snapshot.
     */
    private function captureSnapshot(RouterCollector $collector): RouterSnapshot
    {
        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'Started collector must capture a snapshot.',
        );

        return $snapshot;
    }

    /**
     * Creates a started collector wired to a debug module on top of a mocked web application.
     *
     * @return RouterCollector Started collector.
     */
    private function makeCollector(): RouterCollector
    {
        $this->mockWebApplication();

        $module = new Module('debug');

        $module->logTarget = new LogTarget($module);

        $collector = new RouterCollector();

        $collector->module = $module;

        $collector->startup();

        return $collector;
    }
}
