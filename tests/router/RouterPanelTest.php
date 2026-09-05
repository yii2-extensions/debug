<?php

declare(strict_types=1);

namespace yii\debug\tests\router;

use PHPForge\Debug\Panel\Router\RouterSnapshot;
use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\RouterPanel;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

/**
 * Unit tests for {@see RouterPanel} covering the rendered detail, the toolbar chip, and snapshot hydration.
 */
#[Group('panel')]
#[Group('router')]
final class RouterPanelTest extends TestCase
{
    public function testGetDetailRendersWithCapturedData(): void
    {
        $panel = $this->makePanel(
            RouterPanel::class,
        );

        $this->hydratePanel(
            $panel,
            RouterSnapshot::capture('app\\controllers\\SiteController::actionIndex()', [], 'site/index'),
        );

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
        $panel = $this->makePanel(
            RouterPanel::class,
        );

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
        $panel = $this->makePanel(
            RouterPanel::class,
        );

        $this->hydratePanel(
            $panel,
            RouterSnapshot::capture('app\\controllers\\SiteController::actionIndex()', [], 'site/index'),
        );

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
        $panel = $this->makePanel(
            RouterPanel::class,
        );

        $this->hydratePanel(
            $panel,
            RouterSnapshot::capture(null, [], 'site/index'),
        );

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

    public function testHiddenRouterRetainsItsSnapshotDetailToolbarAndDirectUrl(): void
    {
        $panel = $this->makePanel(
            RouterPanel::class,
        );
        $panel->id = 'router';
        $panel->tag = 'capture-tag';
        $panel->standalone = false;

        $this->hydratePanel(
            $panel,
            RouterSnapshot::capture('app\\controllers\\SiteController::actionIndex()', [], 'site/index'),
        );

        self::assertFalse($panel->isVisible(), 'A non-standalone Router panel must opt out of shared navigation.');
        self::assertSame(
            'site/index',
            $panel->getSnapshot()?->route,
            'A hidden Router panel must keep its captured snapshot available for Request composition.',
        );
        self::assertNotEmpty($panel->getDetail(), 'Visibility must not remove the legacy detail renderer.');
        self::assertNotEmpty($panel->getToolbarData(), 'Visibility must not change direct legacy toolbar generation.');
        self::assertStringContainsString(
            'panel=router',
            $panel->getUrl(),
            'Visibility must not remove the direct Router detail URL.',
        );
    }

    public function testHydrateRestoresTheRuleTraceFromTheSnapshot(): void
    {
        $panel = $this->makePanel(
            RouterPanel::class,
        );

        $captured = RouterSnapshot::capture(
            'app\\controllers\\SiteController::actionIndex()',
            [
                ['Request parsed', Logger::LEVEL_TRACE, 'yii\\web\\UrlManager::parseRequest', 0.0, [], 0],
                [['rule' => 'site/<action>', 'match' => true, 'parent' => ''], 999],
            ],
            'site/index',
        );

        $this->hydratePanel(
            $panel,
            $captured,
        );

        $detail = $panel->getDetail();

        self::assertStringContainsString(
            'site/&lt;action&gt;',
            $detail,
            'The matched rule must render.',
        );
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

        self::assertSame(
            'site/<action>',
            $row->rule,
            'Rule name must round-trip.',
        );
        self::assertSame(
            'parent-rule',
            $row->parent,
            'Parent rule must round-trip.',
        );
        self::assertTrue(
            $row->match,
            'Match flag must round-trip.',
        );
        self::assertTrue(
            $restored->hasMatch(),
            'A matching rule must raise the snapshot flag.',
        );
    }

    public function testStandaloneVisibilityDefaultsToTrue(): void
    {
        self::assertTrue(
            (new RouterPanel())->isVisible(),
            'Direct RouterPanel configurations must retain their legacy standalone visibility.',
        );
    }
}
