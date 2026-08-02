<?php

declare(strict_types=1);

namespace yii\debug\tests\router;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\base\{Action, InlineAction};
use yii\debug\LogTarget;
use yii\debug\panels\router\RouterSnapshot;
use yii\debug\panels\RouterPanel;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;
use yii\web\Controller;

/**
 * Unit tests for {@see RouterPanel} covering the routing trace capture, the action / route narrowing, the toolbar chip,
 * the category-list extension, and snapshot hydration.
 */
#[Group('panel')]
#[Group('router')]
final class RouterPanelTest extends TestCase
{
    public function testCaptureBuildsActionFromInlineAction(): void
    {
        $panel = $this->makePanel(RouterPanel::class);

        $controller = new Controller('site', Yii::$app);
        $action = new InlineAction('index', $controller, 'actionIndex');

        Yii::$app->requestedAction = $action;

        $snapshot = $panel->capture();

        self::assertSame(
            $controller::class . '::actionIndex()',
            $snapshot->action,
            "Inline action must format as 'ControllerFQCN::actionMethod()'.",
        );
        self::assertSame(
            'site/index',
            $snapshot->route,
            'Route must echo the action unique id.',
        );
    }

    public function testCaptureBuildsActionFromRegularAction(): void
    {
        $panel = $this->makePanel(RouterPanel::class);

        $controller = new Controller('site', Yii::$app);

        $action = new class ('run', $controller) extends Action {
            public function run(): void {}
        };

        Yii::$app->requestedAction = $action;

        $snapshot = $panel->capture();

        self::assertSame(
            $action::class . '::run()',
            $snapshot->action,
            "Regular action must format as 'ActionFQCN::run()'.",
        );
    }

    public function testCaptureCapturesFilteredLogMessages(): void
    {
        $panel = $this->makePanel(RouterPanel::class);

        $module = $panel->module ?? self::fail('Module must be wired.');

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

        $snapshot = $panel->capture();

        self::assertSame(
            'matched-rule',
            $snapshot->message,
            'Only categories declared in $categories must survive; the last one wins.',
        );
        self::assertSame([], $snapshot->entries(), 'Plain string traces carry no rule rows.');
    }

    public function testCaptureLeavesActionAsNullWhenNoRequestedAction(): void
    {
        $panel = $this->makePanel(RouterPanel::class);

        Yii::$app->requestedAction = null;
        Yii::$app->requestedRoute = 'site/default';

        $snapshot = $panel->capture();

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
    public function testGetCategoriesReturnsDefaultCategories(): void
    {
        $panel = $this->makePanel(RouterPanel::class);

        self::assertSame(
            [
                'yii\rest\UrlRule::parseRequest',
                'yii\web\CompositeUrlRule::parseRequest',
                'yii\web\UrlManager::parseRequest',
                'yii\web\UrlRule::parseRequest',
            ],
            $panel->getCategories(),
            'Default categories must match the URL manager rule probes.',
        );
    }

    public function testGetDetailRendersWithCapturedData(): void
    {
        $panel = $this->makePanel(RouterPanel::class);

        $this->hydratePanel($panel, RouterSnapshot::capture('app\\controllers\\SiteController::actionIndex()', [], 'site/index'));

        $detail = $panel->getDetail();

        self::assertMatchesRegularExpression(
            '~id="router-panel-0"(?:(?!id="router-panel-1").)*site/index~s',
            $detail,
            'Route must render inside the active pane.',
        );
        self::assertStringContainsString(
            'Resolved route',
            $detail,
            'Summary label must surface in the detail.',
        );
        self::assertStringContainsString(
            'Dispatched action',
            $detail,
            'Action label must surface in the detail.',
        );
    }

    public function testGetNameAndIcon(): void
    {
        $panel = $this->makePanel(RouterPanel::class);

        self::assertSame(
            'Router',
            $panel->getName(),
            "Display name must be 'Router'.",
        );
        self::assertSame(
            'router',
            $panel->getToolbarIcon(),
            "Icon key must be 'router'.",
        );
    }





    public function testGetToolbarItemsFormatsTitleAndValue(): void
    {
        $panel = $this->makePanel(RouterPanel::class);

        $this->hydratePanel($panel, RouterSnapshot::capture('app\\controllers\\SiteController::actionIndex()', [], 'site/index'));

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Toolbar items must be an array.',
        );

        $first = $items[0] ?? self::fail('Expected one toolbar item.');

        self::assertIsArray(
            $first,
            'Item must be an array.',
        );
        self::assertSame(
            'Action: app\\controllers\\SiteController::actionIndex()',
            $first['title'] ?? null,
            'Title must echo the captured action.',
        );
        self::assertSame(
            'site/index',
            $first['value'] ?? null,
            'Value must echo the resolved route.',
        );
    }

    public function testGetToolbarItemsLeavesActionEmptyWhenMissing(): void
    {
        $panel = $this->makePanel(RouterPanel::class);

        $this->hydratePanel($panel, RouterSnapshot::capture(null, [], 'site/index'));

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Toolbar items must be an array.',
        );

        $first = $items[0] ?? self::fail('Expected one toolbar item.');

        self::assertIsArray(
            $first,
            'Item must be an array.',
        );
        self::assertSame(
            'Action: ',
            $first['title'] ?? null,
            'Null action must render as a blank title suffix.',
        );
    }

    public function testHydrateRestoresTheRuleTraceFromTheSnapshot(): void
    {
        $panel = $this->makePanel(RouterPanel::class);

        $captured = RouterSnapshot::capture(
            'app\\controllers\\SiteController::actionIndex()',
            [
                ['Request parsed', Logger::LEVEL_TRACE, 'yii\\web\\UrlManager::parseRequest', 0.0, [], 0],
                [['rule' => 'site/<action>', 'match' => true, 'parent' => ''], 999],
            ],
            'site/index',
        );

        $this->hydratePanel($panel, $captured);

        $detail = $panel->getDetail();

        self::assertStringContainsString('site/&lt;action&gt;', $detail, 'The matched rule must render.');
    }

    public function testRouterSnapshotRoundTripsItsRuleRows(): void
    {
        $captured = RouterSnapshot::capture(
            null,
            [[['rule' => 'site/<action>', 'match' => true, 'parent' => 'parent-rule'], 999]],
            'site/index',
        );

        $restored = RouterSnapshot::fromArray($captured->jsonSerialize(), '$.panels.router');

        $row = $restored->entries()[0] ?? self::fail('Expected one rule row.');

        self::assertSame('site/<action>', $row->rule, 'Rule name must round-trip.');
        self::assertSame('parent-rule', $row->parent, 'Parent rule must round-trip.');
        self::assertTrue($row->match, 'Match flag must round-trip.');
        self::assertTrue($restored->hasMatch(), 'A matching rule must raise the snapshot flag.');
    }




    public function testSetCategoriesAppendsArrayValues(): void
    {
        $panel = $this->makePanel(RouterPanel::class);

        $panel->setCategories(['custom\\Probe::parseRequest', 'another\\Probe::parseRequest']);

        $categories = $panel->getCategories();

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
        $panel = $this->makePanel(RouterPanel::class);

        $panel->setCategories('custom\\Probe::parseRequest');

        self::assertContains(
            'custom\\Probe::parseRequest',
            $panel->getCategories(),
            'Single string must be appended to the category list.',
        );
    }

    public function testSetCategoriesFiltersNonStringEntries(): void
    {
        $panel = $this->makePanel(RouterPanel::class);

        $beforeCount = count($panel->getCategories());

        /** @phpstan-ignore-next-line argument.type */
        $panel->setCategories(['custom\\Probe::parseRequest', 42, null, 'another\\Probe::parseRequest']);

        $categories = $panel->getCategories();

        self::assertCount(
            $beforeCount + 2,
            $categories,
            'Non-string entries must be dropped during append.',
        );
        self::assertContains(
            'custom\\Probe::parseRequest',
            $categories,
            'First string entry must survive.',
        );
        self::assertContains(
            'another\\Probe::parseRequest',
            $categories,
            'Second string entry must survive.',
        );
    }
}
