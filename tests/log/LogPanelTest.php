<?php

declare(strict_types=1);

namespace yii\debug\tests\log;

use PHPForge\Debug\Panel\Log\LogSnapshot;
use PHPForge\Debug\Storage\HydrationException;
use PHPUnit\Framework\Attributes\Group;
use ReflectionMethod;
use yii\debug\panels\LogPanel;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

/**
 * Unit tests for {@see LogPanel} covering payload narrowing, toolbar items per level, the rendered detail and summary
 * views, and the typed row decoration with previous/next ids.
 */
#[Group('panel')]
#[Group('log')]
final class LogPanelTest extends TestCase
{
    public function testCapturePreservesTimestampsAndPreviousRowDelta(): void
    {
        $rows = LogSnapshot::capture(
            [
                ['first', Logger::LEVEL_INFO, 'application', 2.5, []],
                ['second', Logger::LEVEL_INFO, 'application', 4.0, []],
            ],
        )->entries();

        $first = $rows[0] ?? self::fail('Expected the first captured row.');
        $second = $rows[1] ?? self::fail('Expected the second captured row.');

        self::assertSame(
            2500.0,
            $first->time,
            'The tuple timestamp must be read from index three.',
        );
        self::assertSame(
            2500.0,
            $first->timeOfPrevious,
            'The first row must reference its own timestamp.',
        );
        self::assertSame(
            4000.0,
            $second->time,
            'The second tuple timestamp must remain intact.',
        );
        self::assertSame(
            2500.0,
            $second->timeOfPrevious,
            'The second row must reference the first timestamp.',
        );
        self::assertSame(
            1.5,
            $second->timeSincePrevious,
            'The elapsed time must compare adjacent rows.',
        );
    }

    public function testGetDetailRendersErrorAndWarningCountersWhenLevelsArePresent(): void
    {
        $panel = $this->makePanel(
            LogPanel::class,
        );

        $this->hydratePanel(
            $panel,
            LogSnapshot::capture(
                [
                    ['oops', Logger::LEVEL_ERROR, 'application', 1.0, []],
                    ['careful', Logger::LEVEL_WARNING, 'application', 2.0, []],
                    ['hello', Logger::LEVEL_INFO, 'application', 3.0, []],
                ],
            ),
        );

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
        $panel = $this->makePanel(
            LogPanel::class,
        );

        $this->hydratePanel(
            $panel,
            LogSnapshot::capture(
                [
                    ['hello', Logger::LEVEL_INFO, 'application', 0.0, []],
                ],
            ),
        );

        self::assertNotEmpty(
            $panel->getDetail(),
            'Detail view must produce markup.',
        );
    }

    public function testGetMessagesReflectsTheLatestHydration(): void
    {
        $panel = $this->makePanel(
            LogPanel::class,
        );

        $this->hydratePanel(
            $panel,
            LogSnapshot::capture(
                [
                    ['a', Logger::LEVEL_INFO, 'application', 0.0, []],
                ],
            ),
        );

        self::assertCount(
            1,
            $panel->getMessages(),
            'Single message must yield one row.',
        );

        $this->hydratePanel(
            $panel,
            LogSnapshot::capture(
                [
                    ['a', Logger::LEVEL_INFO, 'application', 0.0, []],
                    ['b', Logger::LEVEL_INFO, 'application', 0.0, []],
                ],
            ),
        );

        self::assertCount(
            2,
            $panel->getMessages(),
            'Re-hydration must replace the previous rows.',
        );
    }

    public function testGetMessagesReturnsEmptyListBeforeHydration(): void
    {
        $panel = $this->makePanel(
            LogPanel::class,
        );

        self::assertSame(
            [],
            $panel->getMessages(),
            'An un-hydrated panel exposes no rows.',
        );
    }

    public function testGetModelsCachesAndDecoratesPrevNextIds(): void
    {
        $panel = $this->makePanel(
            LogPanel::class,
        );

        $this->hydratePanel(
            $panel,
            LogSnapshot::capture(
                [
                    ['a', Logger::LEVEL_INFO, 'application', 1.0, []],
                    ['b', Logger::LEVEL_WARNING, 'application', 2.0, []],
                    ['c', Logger::LEVEL_ERROR, 'application', 3.0, []],
                ],
            ),
        );

        $rows = $panel->getMessages();

        self::assertSame(
            $rows,
            $panel->getMessages(),
            'Repeated reads must return the same rows.',
        );

        $row = $rows[1] ?? self::fail("Expected row id '2'.");

        self::assertSame(
            2,
            $row->id,
            "Middle row must carry id '2'.",
        );
        self::assertSame(
            1,
            $row->idOfPrevious,
            "Middle row must point back to id '1'.",
        );
        self::assertSame(
            3,
            $row->idOfNext,
            "Middle row must point forward to id '3'.",
        );
    }

    public function testGetModelsLastRowExposesNullAsNextId(): void
    {
        $panel = $this->makePanel(
            LogPanel::class,
        );

        $this->hydratePanel(
            $panel,
            LogSnapshot::capture(
                [
                    ['a', Logger::LEVEL_INFO, 'application', 1.0, []],
                    ['b', Logger::LEVEL_INFO, 'application', 2.0, []],
                ],
            ),
        );

        $rows = $panel->getMessages();

        $last = $rows[1] ?? self::fail("Expected row id '2'.");

        self::assertNull(
            $last->idOfNext,
            "Last row must expose 'null' as the next id.",
        );
        self::assertSame(
            1,
            $last->idOfPrevious,
            "Last row must point back to id '1'.",
        );
    }

    public function testGetModelsRemainsProtected(): void
    {
        self::assertTrue(
            (new ReflectionMethod(LogPanel::class, 'getModels'))->isProtected(),
            'Must remain protected to avoid accidental misuse.',
        );
    }

    public function testGetModelsScalesTimeToMilliseconds(): void
    {
        $panel = $this->makePanel(
            LogPanel::class,
        );

        $this->hydratePanel(
            $panel,
            LogSnapshot::capture(
                [
                    ['msg', Logger::LEVEL_INFO, 'application', 2.5, []],
                ],
            ),
        );

        $row = $panel->getMessages()[0] ?? self::fail("Expected row id '1'.");

        self::assertEqualsWithDelta(
            2500.0,
            $row->time,
            1e-9,
            'Time must be scaled to milliseconds.',
        );
    }

    public function testGetNameAndIcon(): void
    {
        $panel = $this->makePanel(
            LogPanel::class,
        );

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
        $panel = $this->makePanel(
            LogPanel::class,
        );

        $this->hydratePanel(
            $panel,
            LogSnapshot::capture(
                [
                    ['a', Logger::LEVEL_INFO, 'application', 0.0, []],
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
        $panel = $this->makePanel(
            LogPanel::class,
        );

        $this->hydratePanel(
            $panel,
            LogSnapshot::capture(
                [
                    ['err', Logger::LEVEL_ERROR, 'application', 0.0, []],
                    ['info', Logger::LEVEL_INFO, 'application', 0.0, []],
                ],
            ),
        );

        self::assertSame(
            [
                ['value' => 2],
                [
                    'label' => 'Errors',
                    'status' => 'danger',
                    'url' => $panel->getUrl(['Log[level]' => Logger::LEVEL_ERROR]),
                    'value' => 1,
                ],
            ],
            $this->invoke($panel, 'getToolbarItems'),
        );
    }

    public function testGetToolbarItemsEmitsWarningChipWhenWarningsPresent(): void
    {
        $panel = $this->makePanel(
            LogPanel::class,
        );

        $this->hydratePanel(
            $panel,
            LogSnapshot::capture(
                [
                    ['warn', Logger::LEVEL_WARNING, 'application', 0.0, []],
                ],
            ),
        );

        self::assertSame(
            [
                ['value' => 1],
                [
                    'label' => 'Warnings',
                    'status' => 'warning',
                    'url' => $panel->getUrl(['Log[level]' => Logger::LEVEL_WARNING]),
                    'value' => 1,
                ],
            ],
            $this->invoke($panel, 'getToolbarItems'),
        );
    }

    public function testHydrationRejectsNonArrayMessages(): void
    {
        $panel = $this->makePanel(
            LogPanel::class,
        );

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '$.panels..entries': expected a required field.",
        );

        $panel->hydrate(['messages' => 'corrupt']);
    }
}
