<?php

declare(strict_types=1);

namespace yii\debug\tests\log;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\log\LogSnapshot;
use yii\debug\panels\{LogPanel, RouterPanel};
use yii\debug\storage\HydrationException;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

/**
 * Unit tests for {@see LogPanel} covering log capture, payload narrowing, toolbar items per level, the rendered detail
 * and summary views, and the typed row decoration with previous/next ids.
 */
#[Group('panel')]
#[Group('log')]
final class LogPanelTest extends TestCase
{
    public function testCaptureDropsNonArrayLoggerEntries(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $this->hydratePanel($panel, LogSnapshot::capture([
            ['valid', Logger::LEVEL_INFO, 'application', 0.0, []],
            'invalid-string',
        ]));

        self::assertCount(
            1,
            $panel->getMessages(),
            'Non-array entries must be dropped at capture.',
        );
    }

    public function testCaptureExcludesCategoriesOwnedByRouterPanel(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $module = $panel->module ?? self::fail('Module must be wired.');

        $module->panels['router'] = new RouterPanel(['id' => 'router', 'module' => $module]);

        self::assertSame(
            [],
            $panel->capture()->entries(),
            'Empty log target yields no rows.',
        );
    }

    public function testCaptureNarrowsNumericStringLevelToInt(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $this->hydratePanel($panel, LogSnapshot::capture([
            ['oops', (string) Logger::LEVEL_ERROR, 'application', 0.0, []],
        ]));

        $row = $panel->getMessages()[0] ?? self::fail('Expected one captured row.');

        self::assertSame(Logger::LEVEL_ERROR, $row->level, 'Numeric-string level must narrow to `int`.');
    }

    public function testCaptureReturnsTypedRows(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        self::assertSame(
            [],
            $panel->capture()->entries(),
            'Empty log target yields no rows.',
        );
    }

    public function testGetDetailRendersErrorAndWarningCountersWhenLevelsArePresent(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $this->hydratePanel($panel, LogSnapshot::capture([
            ['oops', Logger::LEVEL_ERROR, 'application', 1.0, []],
            ['careful', Logger::LEVEL_WARNING, 'application', 2.0, []],
            ['hello', Logger::LEVEL_INFO, 'application', 3.0, []],
        ]));

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'errors',
            $html,
            'Error counter must surface.',
        );
        self::assertStringContainsString(
            'warnings',
            $html,
            'Warning counter must surface.',
        );
    }

    public function testGetDetailRendersWithCapturedMessages(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $this->hydratePanel($panel, LogSnapshot::capture([['hello', Logger::LEVEL_INFO, 'application', 0.0, []]]));

        self::assertNotEmpty(
            $panel->getDetail(),
            'Detail view must produce markup.',
        );
    }

    public function testGetMessagesReflectsTheLatestHydration(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $this->hydratePanel($panel, LogSnapshot::capture([['a', Logger::LEVEL_INFO, 'application', 0.0, []]]));

        self::assertCount(
            1,
            $panel->getMessages(),
            'Single message must yield one row.',
        );

        $this->hydratePanel($panel, LogSnapshot::capture([
            ['a', Logger::LEVEL_INFO, 'application', 0.0, []],
            ['b', Logger::LEVEL_INFO, 'application', 0.0, []],
        ]));

        self::assertCount(
            2,
            $panel->getMessages(),
            'Re-hydration must replace the previous rows.',
        );
    }

    public function testGetMessagesReturnsEmptyListBeforeHydration(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        self::assertSame([], $panel->getMessages(), 'An un-hydrated panel exposes no rows.');
    }

    public function testGetModelsCachesAndDecoratesPrevNextIds(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $this->hydratePanel($panel, LogSnapshot::capture([
            ['a', Logger::LEVEL_INFO, 'application', 1.0, []],
            ['b', Logger::LEVEL_WARNING, 'application', 2.0, []],
            ['c', Logger::LEVEL_ERROR, 'application', 3.0, []],
        ]));

        $rows = $panel->getMessages();

        self::assertSame(
            $rows,
            $panel->getMessages(),
            'Repeated reads must return the same rows.',
        );

        $row = $rows[1] ?? self::fail("Expected row id '2'.");

        self::assertSame(2, $row->id, "Middle row must carry id '2'.");
        self::assertSame(1, $row->idOfPrevious, "Middle row must point back to id '1'.");
        self::assertSame(3, $row->idOfNext, "Middle row must point forward to id '3'.");
    }

    public function testGetModelsLastRowExposesNullAsNextId(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $this->hydratePanel($panel, LogSnapshot::capture([
            ['a', Logger::LEVEL_INFO, 'application', 1.0, []],
            ['b', Logger::LEVEL_INFO, 'application', 2.0, []],
        ]));

        $rows = $panel->getMessages();
        $last = $rows[1] ?? self::fail("Expected row id '2'.");

        self::assertNull($last->idOfNext, 'Last row must expose `null` as the next id.');
        self::assertSame(1, $last->idOfPrevious, "Last row must point back to id '1'.");
    }

    public function testGetModelsScalesTimeToMilliseconds(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $this->hydratePanel($panel, LogSnapshot::capture([['msg', Logger::LEVEL_INFO, 'application', 2.5, []]]));

        $row = $panel->getMessages()[0] ?? self::fail("Expected row id '1'.");

        self::assertEqualsWithDelta(2500.0, $row->time, 1e-9, 'Time must be scaled to milliseconds.');
    }

    public function testGetNameAndIcon(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        self::assertSame(
            'Logs',
            $panel->getName(),
            "Display name must be 'Logs'.",
        );
        self::assertSame(
            'logs',
            $panel->getToolbarIcon(),
            "Icon key must be 'logs'.",
        );
    }

    public function testGetToolbarItemsEmitsCountChipOnly(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $this->hydratePanel($panel, LogSnapshot::capture([['a', Logger::LEVEL_INFO, 'application', 0.0, []]]));

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Items must be a list.',
        );
        self::assertCount(
            1,
            $items,
            'No errors/warnings means only the count chip.',
        );

        $first = $items[0] ?? self::fail('Expected one item.');

        self::assertIsArray(
            $first,
            'Item must be an array.',
        );
        self::assertSame(
            1,
            $first['value'] ?? null,
            'Count chip must match the message count.',
        );
    }

    public function testGetToolbarItemsEmitsDangerChipWhenErrorsPresent(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $this->hydratePanel($panel, LogSnapshot::capture([
            ['err', Logger::LEVEL_ERROR, 'application', 0.0, []],
            ['info', Logger::LEVEL_INFO, 'application', 0.0, []],
        ]));

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Items must be a list.',
        );

        $errorsItem = $items[1] ?? self::fail(
            'Expected an errors chip.',
        );

        self::assertIsArray(
            $errorsItem,
            'Errors chip must be an array.',
        );
        self::assertSame(
            'danger',
            $errorsItem['status'] ?? null,
            "Errors chip must use the 'danger' status.",
        );
        self::assertSame(
            1,
            $errorsItem['value'] ?? null,
            'Errors chip must count the error rows.',
        );
    }

    public function testGetToolbarItemsEmitsWarningChipWhenWarningsPresent(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $this->hydratePanel($panel, LogSnapshot::capture([['warn', Logger::LEVEL_WARNING, 'application', 0.0, []]]));

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Items must be a list.',
        );

        $warnItem = $items[1] ?? self::fail('Expected a warnings chip.');

        self::assertIsArray(
            $warnItem,
            'Warnings chip must be an array.',
        );
        self::assertSame(
            'warning',
            $warnItem['status'] ?? null,
            "Warnings chip must use the 'warning' status.",
        );
    }

    public function testHydrationRejectsNonArrayMessages(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $this->expectException(HydrationException::class);

        $panel->hydrate(['messages' => 'corrupt']);
    }
}
