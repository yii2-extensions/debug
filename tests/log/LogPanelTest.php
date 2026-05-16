<?php

declare(strict_types=1);

namespace yii\debug\tests\log;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\{LogPanel, RouterPanel};
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
    public function testGetDetailRendersWithCapturedMessages(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $panel->data = [
            'messages' => [['hello', Logger::LEVEL_INFO, 'application', 0.0, []]],
        ];

        self::assertNotEmpty(
            $panel->getDetail(),
            'Detail view must produce markup.',
        );
    }

    public function testGetModelsCachesAndDecoratesPrevNextIds(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $panel->data = [
            'messages' => [
                ['a', Logger::LEVEL_INFO, 'application', 1.0, []],
                ['b', Logger::LEVEL_WARNING, 'application', 2.0, []],
                ['c', Logger::LEVEL_ERROR, 'application', 3.0, []],
            ],
        ];

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
        self::assertIsArray(
            $first,
            'Models must be an array.',
        );

        $row = $first[2] ?? self::fail("Expected row id '2'.");

        self::assertIsArray(
            $row,
            'Row must be an array.',
        );
        self::assertSame(
            1,
            $row['id_of_previous'] ?? null,
            "Middle row must point back to id '1'.",
        );
        self::assertSame(
            3,
            $row['id_of_next'] ?? null,
            "Middle row must point forward to id '3'.",
        );
    }

    public function testGetModelsLastRowExposesNullAsNextId(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $panel->data = [
            'messages' => [
                ['a', Logger::LEVEL_INFO, 'application', 1.0, []],
                ['b', Logger::LEVEL_INFO, 'application', 2.0, []],
            ],
        ];

        $models = $this->invoke(
            $panel,
            'getModels',
        );

        self::assertIsArray(
            $models,
            'Models must be an array.',
        );

        $last = $models[2] ?? self::fail("Expected row id '2'.");

        self::assertIsArray(
            $last,
            'Row must be an array.',
        );
        self::assertArrayHasKey(
            'id_of_next',
            $last,
            "Last row must declare an 'id_of_next' slot.",
        );
        self::assertNull(
            $last['id_of_next'],
            "Last row must expose 'null' next id.",
        );
    }

    public function testGetModelsRebuildsCacheWhenRefreshIsTrue(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $panel->data = [
            'messages' => [['a', Logger::LEVEL_INFO, 'application', 0.0, []]],
        ];

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

        $panel->data = [
            'messages' => [
                ['a', Logger::LEVEL_INFO, 'application', 0.0, []],
                ['b', Logger::LEVEL_INFO, 'application', 0.0, []],
            ],
        ];

        $refreshed = $this->invoke(
            $panel,
            'getModels',
            [true]
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
        $panel = $this->makePanel(LogPanel::class);

        $panel->data = [
            'messages' => [['msg', Logger::LEVEL_INFO, 'application', 2.5, []]],
        ];

        $models = $this->invoke(
            $panel,
            'getModels',
        );

        self::assertIsArray(
            $models,
            'Models must be an array.',
        );

        $row = $models[1] ?? self::fail("Expected row id '1'.");

        self::assertIsArray(
            $row,
            'Row must be an array.',
        );
        self::assertEqualsWithDelta(
            2500.0,
            $row['time'] ?? null,
            1e-9,
            'Time must be scaled to milliseconds.',
        );
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

    public function testGetSavedMessagesDropsNonArrayEntries(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $this->setInaccessibleProperty(
            $panel,
            'data',
            [
                'messages' => [
                    ['valid', Logger::LEVEL_INFO, 'application', 0.0, []],
                    'invalid-string',
                ],
            ],
        );

        $messages = $this->invoke(
            $panel,
            'getSavedMessages',
        );

        self::assertIsArray(
            $messages,
            'Messages must be an array.',
        );
        self::assertCount(
            1,
            $messages,
            'Non-array entries must be dropped.',
        );
    }

    public function testGetSavedMessagesReturnsEmptyWhenDataIsNotArray(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $this->setInaccessibleProperty(
            $panel,
            'data',
            'corrupt',
        );

        self::assertSame(
            [],
            $this->invoke(
                $panel,
                'getSavedMessages',
            ),
            "Non-array data must collapse to '[]'.",
        );
    }

    public function testGetSavedMessagesReturnsEmptyWhenMessagesKeyIsNotArray(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $this->setInaccessibleProperty(
            $panel,
            'data',
            ['messages' => 'corrupt'],
        );

        self::assertSame(
            [],
            $this->invoke(
                $panel,
                'getSavedMessages',
            ),
            "Non-array 'messages' key must collapse to '[]'.",
        );
    }

    public function testGetSummaryRendersChip(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $panel->data = [
            'messages' => [['a', Logger::LEVEL_INFO, 'application', 0.0, []]],
        ];

        self::assertStringContainsString(
            'Log',
            $panel->getSummary(),
            'Chip must render the panel label.',
        );
    }

    public function testGetToolbarItemsEmitsCountChipOnly(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $panel->data = [
            'messages' => [['a', Logger::LEVEL_INFO, 'application', 0.0, []]],
        ];

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

        $panel->data = [
            'messages' => [
                ['err', Logger::LEVEL_ERROR, 'application', 0.0, []],
                ['info', Logger::LEVEL_INFO, 'application', 0.0, []],
            ],
        ];

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

        $panel->data = [
            'messages' => [['warn', Logger::LEVEL_WARNING, 'application', 0.0, []]],
        ];

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

    public function testNormalizeStringListDropsNonStringAndFallsBackOnNonArray(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        self::assertSame(
            ['kept-a', 'kept-b'],
            $this->invoke(
                $panel,
                'normalizeStringList',
                [['kept-a', 42, null, 'kept-b']],
            ),
            'Only string entries must survive.',
        );
        self::assertSame(
            [],
            $this->invoke(
                $panel,
                'normalizeStringList',
                ['not-an-array'],
            ),
            "Non-array input must collapse to '[]'.",
        );
    }

    public function testSaveExcludesCategoriesOwnedByRouterPanel(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $module = $panel->module ?? self::fail('Module must be wired.');

        $module->panels['router'] = new RouterPanel(['id' => 'router', 'module' => $module]);

        $payload = $panel->save();

        self::assertSame(
            [],
            $payload['messages'],
            'Empty log target yields no messages.',
        );
    }

    public function testSaveReturnsMessagesKey(): void
    {
        $panel = $this->makePanel(LogPanel::class);

        $payload = $panel->save();

        self::assertSame(
            [],
            $payload['messages'],
            'Empty log target yields no messages.',
        );
    }
}
