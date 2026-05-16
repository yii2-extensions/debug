<?php

declare(strict_types=1);

namespace yii\debug\tests\router;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\base\{Action, InlineAction};
use yii\debug\LogTarget;
use yii\debug\panels\RouterPanel;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;
use yii\web\Controller;

/**
 * Unit tests for {@see RouterPanel} covering the routing trace capture, the action / route narrowing, the toolbar chip,
 * the category-list extension, and the saved-payload narrowing.
 */
#[Group('panel')]
#[Group('router')]
final class RouterPanelTest extends TestCase
{
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

        $panel->data = [
            'action' => 'app\\controllers\\SiteController::actionIndex()',
            'messages' => [],
            'route' => 'site/index',
        ];

        self::assertNotEmpty(
            $panel->getDetail(),
            'Detail view must produce markup.',
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

    public function testGetRouteDataAppliesDefaultsForMissingFields(): void
    {
        $panel = $this->makePanel(RouterPanel::class);

        $this->setInaccessibleProperty(
            $panel,
            'data',
            null,
        );

        $data = $this->invoke(
            $panel,
            'getRouteData',
        );

        self::assertIsArray(
            $data,
            'Route data must be an array.',
        );
        self::assertArrayHasKey(
            'action',
            $data,
            'Action slot must be present.',
        );
        self::assertNull(
            $data['action'],
            "Missing action must default to 'null'.",
        );
        self::assertSame(
            '',
            $data['route'] ?? null,
            "Missing route must default to ''.",
        );
        self::assertSame(
            [],
            $data['messages'] ?? null,
            "Missing messages must default to '[]'.",
        );
    }

    public function testGetRouteDataCoercesScalarActionToString(): void
    {
        $panel = $this->makePanel(RouterPanel::class);

        $this->setInaccessibleProperty(
            $panel,
            'data',
            ['action' => 42, 'route' => 'site/index', 'messages' => []],
        );

        $data = $this->invoke(
            $panel,
            'getRouteData',
        );

        self::assertIsArray(
            $data,
            'Route data must be an array.',
        );
        self::assertSame(
            '42',
            $data['action'] ?? null,
            'Scalar action must be coerced to its string form.',
        );
    }

    public function testGetRouteDataFallsBackToDefaultsWhenDataIsNotArray(): void
    {
        $panel = $this->makePanel(RouterPanel::class);

        $this->setInaccessibleProperty(
            $panel,
            'data',
            'corrupt',
        );

        $data = $this->invoke(
            $panel,
            'getRouteData',
        );

        self::assertSame(
            ['action' => null, 'messages' => [], 'route' => ''],
            $data,
            'Non-array data must collapse to defaults.',
        );
    }

    public function testGetRouteDataNarrowsNonScalarActionToNull(): void
    {
        $panel = $this->makePanel(RouterPanel::class);

        $this->setInaccessibleProperty(
            $panel,
            'data',
            ['action' => ['nested'], 'route' => 'site/index', 'messages' => []],
        );

        $data = $this->invoke(
            $panel,
            'getRouteData',
        );

        self::assertIsArray(
            $data,
            'Route data must be an array.',
        );
        self::assertArrayHasKey(
            'action',
            $data,
            'Action slot must be present.',
        );
        self::assertNull(
            $data['action'],
            "Non-scalar action must collapse to 'null'.",
        );
    }

    public function testGetSummaryRendersChip(): void
    {
        $panel = $this->makePanel(RouterPanel::class);

        $panel->data = [
            'action' => 'app\\controllers\\SiteController::actionIndex()',
            'messages' => [],
            'route' => 'site/index',
        ];

        self::assertStringContainsString(
            'site/index',
            $panel->getSummary(),
            'Summary chip must echo the resolved route.',
        );
    }

    public function testGetToolbarItemsFormatsTitleAndValue(): void
    {
        $panel = $this->makePanel(RouterPanel::class);

        $panel->data = [
            'action' => 'app\\controllers\\SiteController::actionIndex()',
            'messages' => [],
            'route' => 'site/index',
        ];

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

        $panel->data = ['action' => null, 'messages' => [], 'route' => 'site/index'];

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

    public function testNormalizeMessagesFiltersNonArrayEntries(): void
    {
        $panel = $this->makePanel(RouterPanel::class);

        self::assertSame(
            [['kept' => 1], ['kept' => 2]],
            $this->invoke(
                $panel,
                'normalizeMessages',
                [[['kept' => 1], 'drop-string', ['kept' => 2], 42]],
            ),
            'Non-array entries must be dropped.',
        );
    }

    public function testNormalizeMessagesReturnsEmptyArrayForNonArrayInput(): void
    {
        $panel = $this->makePanel(RouterPanel::class);

        self::assertSame(
            [],
            $this->invoke(
                $panel,
                'normalizeMessages',
                ['not-an-array'],
            ),
            "Non-array input must collapse to '[]'.",
        );
    }

    public function testNormalizeStringListFiltersNonStringEntries(): void
    {
        $panel = $this->makePanel(RouterPanel::class);

        self::assertSame(
            ['kept-a', 'kept-b'],
            $this->invoke(
                $panel,
                'normalizeStringList',
                [['kept-a', 42, null, 'kept-b', false]],
            ),
            'Only string entries must survive.',
        );
    }

    public function testSaveBuildsActionFromInlineAction(): void
    {
        $panel = $this->makePanel(RouterPanel::class);

        $controller = new Controller('site', Yii::$app);
        $action = new InlineAction('index', $controller, 'actionIndex');

        Yii::$app->requestedAction = $action;

        $saved = $panel->save();

        self::assertSame(
            $controller::class . '::actionIndex()',
            $saved['action'],
            "Inline action must format as 'ControllerFQCN::actionMethod()'.",
        );
        self::assertSame(
            'site/index',
            $saved['route'],
            'Route must echo the action unique id.',
        );
    }

    public function testSaveBuildsActionFromRegularAction(): void
    {
        $panel = $this->makePanel(RouterPanel::class);

        $controller = new Controller('site', Yii::$app);

        $action = new class ('run', $controller) extends Action {
            public function run(): void {}
        };

        Yii::$app->requestedAction = $action;

        $saved = $panel->save();

        self::assertSame(
            $action::class . '::run()',
            $saved['action'],
            "Regular action must format as 'ActionFQCN::run()'.",
        );
    }

    public function testSaveCapturesFilteredLogMessages(): void
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

        $saved = $panel->save();

        self::assertCount(
            2,
            $saved['messages'],
            'Only categories declared in $categories must survive.',
        );
    }

    public function testSaveLeavesActionAsNullWhenNoRequestedAction(): void
    {
        $panel = $this->makePanel(RouterPanel::class);

        Yii::$app->requestedAction = null;
        Yii::$app->requestedRoute = 'site/default';

        $saved = $panel->save();

        self::assertNull(
            $saved['action'],
            "Missing requested action must yield 'null'.",
        );
        self::assertSame(
            'site/default',
            $saved['route'],
            "Route must fall back to 'requestedRoute'.",
        );
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
