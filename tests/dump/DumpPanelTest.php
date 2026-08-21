<?php

declare(strict_types=1);

namespace yii\debug\tests\dump;

use PHPForge\Debug\Panel\Dump\DumpSnapshot;
use PHPUnit\Framework\Attributes\Group;
use ReflectionMethod;
use yii\debug\panels\DumpPanel;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

/**
 * Unit tests for {@see DumpPanel} covering the typed dump-row narrowing, the toolbar item shortcut, and the rendered
 * detail and summary views.
 */
#[Group('panel')]
#[Group('dump')]
final class DumpPanelTest extends TestCase
{
    public function testGetModelsRemainsProtected(): void
    {
        self::assertTrue(
            (new ReflectionMethod(DumpPanel::class, 'getModels'))->isProtected(),
            'The getModels method must remain protected.',
        );
    }

    public function testGetDetailRendersEmptyStateWhenNoDumpsCaptured(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $this->hydratePanel(
            $panel,
            DumpSnapshot::capture([]),
        );

        $detail = $panel->getDetail();

        self::assertStringContainsString(
            'yii-debug-empty-state',
            $detail,
            'Empty capture must render the empty-state card.',
        );
        self::assertStringContainsString(
            'No variables dumped in this request',
            $detail,
            'Empty-state heading must explain the missing dumps.',
        );
    }

    public function testGetDetailRendersWithCapturedMessages(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $this->hydratePanel(
            $panel,
            DumpSnapshot::capture(
                [
                    ['<pre>42</pre>', Logger::LEVEL_TRACE, 'application', 0.001, []],
                ],
            ),
        );

        $detail = $panel->getDetail();

        self::assertNotEmpty(
            $detail,
            'Detail view must produce markup.',
        );
        self::assertStringContainsString(
            'yii-debug-grid-dump',
            $detail,
            'Grid must carry the dump variant class.',
        );
    }

    public function testGetModelsCachesNormalizedRows(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $this->hydratePanel(
            $panel,
            DumpSnapshot::capture(
                [
                    ['<pre>42</pre>', Logger::LEVEL_TRACE, 'application', 0.5, []],
                ],
            ),
        );

        $first = $this->invoke(
            $panel,
            'getModels',
        );
        $second = $this->invoke(
            $panel,
            'getModels',
        );

        self::assertSame(
            $first,
            $second,
            'Cache must return the same list.',
        );
    }

    public function testGetModelsRebuildsCacheWhenRefreshIsTrue(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $this->hydratePanel(
            $panel,
            DumpSnapshot::capture(
                [
                    ['a', Logger::LEVEL_TRACE, 'application', 0.0, []],
                ],
            ),
        );

        $first = $this->invoke(
            $panel,
            'getModels',
        );

        self::assertIsArray(
            $first,
            'Models must be an array.',
        );
        self::assertCount(
            1,
            $first,
            'Single message must yield one row.',
        );

        $this->hydratePanel(
            $panel,
            DumpSnapshot::capture(
                [
                    ['a', Logger::LEVEL_TRACE, 'application', 0.0, []],
                    ['b', Logger::LEVEL_TRACE, 'application', 0.0, []],
                ],
            ),
        );

        $refreshed = $this->invoke(
            $panel,
            'getModels',
            [true],
        );

        self::assertIsArray(
            $refreshed,
            'Refreshed models must be an array.',
        );
        self::assertCount(
            2,
            $refreshed,
            'Refresh must rebuild from the latest data.',
        );
    }

    public function testGetModelsScalesTimeToMilliseconds(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $this->hydratePanel(
            $panel,
            DumpSnapshot::capture(
                [
                    ['msg', Logger::LEVEL_TRACE, 'application', 2.5, []],
                ],
            ),
        );

        $row = $panel->getDumps()[0] ?? self::fail('Expected one row.');

        self::assertEqualsWithDelta(
            2500.0,
            $row->time,
            1e-9,
            'Time must be scaled to milliseconds.',
        );
    }

    public function testGetModelsTreatsNonArrayDataAsEmpty(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $models = $this->invoke(
            $panel,
            'getModels',
        );

        self::assertSame(
            [],
            $models,
            'Corrupt data must collapse to no rows.',
        );
    }

    public function testGetNameAndIcon(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        self::assertSame(
            'Dump',
            $panel->getName(),
            "Display name must be 'Dump'.",
        );
        self::assertSame(
            'dump',
            $panel->getToolbarIcon(),
            "Icon key must be 'dump'.",
        );
    }

    public function testGetToolbarItemsEmitsCountChipWhenMessagesPresent(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        $this->hydratePanel(
            $panel,
            DumpSnapshot::capture(
                [
                    ['a', Logger::LEVEL_TRACE, 'application', 0.0, []],
                    ['b', Logger::LEVEL_TRACE, 'application', 0.0, []],
                ],
            ),
        );

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Items must be a list.',
        );

        $first = $items[0] ?? self::fail('Expected one item.');

        self::assertIsArray(
            $first,
            'Item must be an array.',
        );
        self::assertSame(
            'info',
            $first['status'] ?? null,
            "Chip status must be 'info'.",
        );
        self::assertSame(
            2,
            $first['value'] ?? null,
            'Value must match the message count.',
        );
    }

    public function testGetToolbarItemsReturnsEmptyArrayWhenDataIsEmpty(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        self::assertSame(
            [],
            $this->invoke(
                $panel,
                'getToolbarItems',
            ),
            'Empty data must skip the toolbar item.',
        );
    }

    public function testGetToolbarItemsReturnsEmptyArrayWhenDataIsNotArray(): void
    {
        $panel = $this->makePanel(DumpPanel::class);

        self::assertSame(
            [],
            $this->invoke(
                $panel,
                'getToolbarItems',
            ),
            'Non-array data must skip the toolbar item.',
        );
    }
}
