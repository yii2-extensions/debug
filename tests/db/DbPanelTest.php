<?php

declare(strict_types=1);

namespace yii\debug\tests\db;

use PHPForge\Debug\Panel\Db\{DbSnapshot, QueryRow};
use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\db\Connection;
use yii\debug\collectors\DbCollector;
use yii\debug\db\DebugPdoStatement;
use yii\debug\LogTarget;
use yii\debug\panels\DbPanel;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

use function is_string;

/**
 * Unit tests for {@see DbPanel} covering EXPLAIN gating, threshold checks,
 * the badge variant mapping, toolbar/summary rendering, and snapshot hydration.
 */
#[Group('panel')]
#[Group('db')]
final class DbPanelTest extends TestCase
{
    public function testGetDbReturnsConfiguredConnection(): void
    {
        $this->mockWebApplication(
            ['components' => ['db' => $this->makeSqliteConnection()]],
        );

        $panel = new DbPanel();

        self::assertSame(
            Yii::$app->get('db'),
            $panel->getDb(),
            'Resolved connection must match the configured component.',
        );
    }

    public function testGetDetailOmitsExplainAllWhenNoQueriesCaptured(): void
    {
        $panel = $this->makePanel(DbPanel::class, ['db' => $this->makeSqliteConnection()]);

        $this->hydrateFromLive($panel, [], []);

        self::assertStringNotContainsString(
            'Explain all',
            $panel->getDetail(),
            'Explain toggle must not render without queries.',
        );
    }

    public function testGetDetailRendersDuplicatedCounterWhenSameQueryRepeats(): void
    {
        $panel = $this->makePanel(DbPanel::class, ['db' => $this->makeSqliteConnection()]);

        $this->hydrateFromLive($panel, [
            ...$this->makeMessage('SELECT 1', 0.001, 0.0),
            ...$this->makeMessage('SELECT 1', 0.001, 0.001),
        ], []);

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'duplicated',
            $html,
            "Repeated queries must surface the 'duplicated' chip.",
        );
    }

    public function testGetDetailRendersEmptyStateWhenNoQueriesCaptured(): void
    {
        $panel = $this->makePanel(DbPanel::class, ['db' => $this->makeSqliteConnection()]);

        $this->hydrateFromLive($panel, [], []);

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'yii-debug-empty-state',
            $html,
            'Zero queries must render the empty-state card.',
        );
        self::assertStringContainsString(
            'No database queries in this request',
            $html,
            'Card headline must describe the empty capture.',
        );
        self::assertStringContainsString(
            'yii-debug-grid-summary',
            $html,
            'Summary strip must render alongside the card.',
        );
    }

    public function testGetDetailRendersWithCapturedMessages(): void
    {
        $panel = $this->makePanel(DbPanel::class, ['db' => $this->makeSqliteConnection()]);

        $this->hydrateFromLive($panel, [...$this->makeMessage('SELECT 1', 0.001, 0.0)], []);

        $html = $panel->getDetail();

        self::assertNotEmpty(
            $html,
            'Detail view must produce non-empty markup.',
        );
    }

    public function testGetDetailUsesHydratedRowsInsteadOfCurrentRequestTimings(): void
    {
        $panel = $this->makePanel(DbPanel::class, ['db' => $this->makeSqliteConnection()]);

        $this->hydrateFromLive($panel, [], []);
        $this->hydratePanel($panel, new DbSnapshot([$this->makeRowWithDuration(1.5)]));

        $html = $panel->getDetail();

        self::assertStringContainsString(
            '<strong>1</strong> queries',
            $html,
            'The detail summary must count rows from the hydrated snapshot.',
        );
        self::assertStringContainsString(
            '<strong>1.500</strong> ms total',
            $html,
            'The detail summary must total durations from the hydrated snapshot.',
        );
        self::assertStringContainsString(
            'SELECT',
            $html,
            'The detail grid must render the hydrated query.',
        );
        self::assertStringNotContainsString(
            'No database queries in this request',
            $html,
            'Hydrated queries must not render the empty-state card.',
        );
    }

    public function testGetExcessiveCallersReturnsCallersAtOrAboveThreshold(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        $this->hydrateFromLive($panel, $this->fakeMessages(3), []);

        $this->setDbCollectorThreshold($panel, 2);

        self::assertCount(
            1,
            $panel->getExcessiveCallers(),
            'Three identical callers must yield one excessive entry.',
        );
        self::assertSame(
            1,
            $panel->getExcessiveCallersCount(),
            'Excessive caller count must be 1.',
        );
    }

    public function testGetExcessiveCallersReturnsEmptyWhenThresholdIsNull(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        $this->hydrateFromLive($panel, $this->fakeMessages(5), []);

        self::assertSame(
            [],
            $panel->getExcessiveCallers(),
            'Null threshold must yield no excessive callers.',
        );
        self::assertSame(
            0,
            $panel->getExcessiveCallersCount(),
            'Null threshold must report zero count.',
        );
    }

    public function testGetNameAndIcon(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        self::assertSame(
            'Database',
            $panel->getName(),
            "Display name must be 'Database'.",
        );
        self::assertSame(
            'db',
            $panel->getToolbarIcon(),
            "Icon key must be 'db'.",
        );
    }

    public function testGetToolbarItemsEmitsWarningForExcessiveCallers(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        $this->hydrateFromLive($panel, [
            ...$this->makeMessage('SELECT 1', 0.001, 0.0, trace: [['file' => '/a.php', 'line' => 1]]),
            ...$this->makeMessage('SELECT 2', 0.001, 0.001, trace: [['file' => '/b.php', 'line' => 1]]),
        ], []);
        $this->setDbCollectorThreshold($panel, 0);

        $first = $this->firstToolbarItem($panel);

        self::assertSame(
            'warning',
            $first['status'] ?? null,
            'Excessive callers must flip the status chip to warning.',
        );
        self::assertIsString(
            $first['title'] ?? null,
            "Toolbar 'title' must be a string.",
        );
        self::assertStringContainsString(
            'callers are',
            $first['title'],
            'Multiple excessive callers must use the plural label.',
        );
    }

    public function testGetToolbarItemsEmitsWarningWhenCriticalThresholdExceeded(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        $this->hydrateFromLive($panel, [...$this->makeMessage('SELECT 1', 0.001, 0.0)], []);
        $panel->criticalQueryThreshold = 0;

        $first = $this->firstToolbarItem($panel);

        self::assertSame(
            'warning',
            $first['status'] ?? null,
            'Critical threshold must flip the status chip to warning.',
        );
    }

    public function testGetToolbarItemsReturnsEmptyArrayWhenNoQueriesCaptured(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        self::assertSame(
            [],
            $this->invoke(
                $panel,
                'getToolbarItems',
            ),
            'Empty timings must yield no toolbar items.',
        );
    }

    public function testGetToolbarItemsUsesSingularLabelForSingleExcessiveCaller(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        $this->hydrateFromLive($panel, [...$this->makeMessage('SELECT 1', 0.001, 0.0)], []);
        $this->setDbCollectorThreshold($panel, 0);

        $first = $this->firstToolbarItem($panel);

        self::assertIsString(
            $first['title'] ?? null,
            "Toolbar 'title' must be a string.",
        );
        self::assertStringContainsString(
            'caller is',
            $first['title'],
            'Single excessive caller must use the singular label.',
        );
    }

    public function testGetTotalQueryTimeSumsDurations(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        $rows = [$this->makeRowWithDuration(1.0), $this->makeRowWithDuration(2.0)];

        self::assertEqualsWithDelta(
            3.0,
            $this->invoke(
                $panel,
                'getTotalQueryTime',
                [$rows],
            ),
            1e-9,
            'Total query time must equal the sum of durations, in milliseconds.',
        );
    }

    public function testGetTypesReturnsDropdownMap(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        $this->hydrateFromLive($panel, [
            ...$this->makeMessage('SELECT * FROM t', 0.001, 0.0),
            ...$this->makeMessage('INSERT INTO t VALUES (1)', 0.002, 0.001),
            ...$this->makeMessage('SELECT id FROM t', 0.003, 0.003),
        ], []);

        $types = $panel->getTypes();

        self::assertArrayHasKey(
            'SELECT',
            $types,
            'Captured SELECT statements must appear in the types map.',
        );
        self::assertArrayHasKey(
            'INSERT',
            $types,
            'Captured INSERT statements must appear in the types map.',
        );
        self::assertSame(
            'SELECT',
            $types['SELECT'],
            'Type map must be keyed and valued by the same verb.',
        );
    }

    public function testHasExplainReturnsFalseWhenDbCannotBeResolved(): void
    {
        $this->mockWebApplication();

        $panel = new DbPanel();
        $panel->db = 'absent';

        self::assertFalse(
            $this->invoke(
                $panel,
                'hasExplain',
            ),
            'Missing DB component must collapse to no EXPLAIN.',
        );
    }

    public function testHasExplainReturnsTrueForSqlite(): void
    {
        $this->mockWebApplication(
            ['components' => ['db' => $this->makeSqliteConnection()]],
        );

        $panel = new DbPanel();

        self::assertTrue(
            $this->invoke(
                $panel,
                'hasExplain',
            ),
            'SQLite driver must support EXPLAIN.',
        );
    }

    public function testInitRegistersExplainAction(): void
    {
        $this->mockWebApplication(
            ['components' => ['db' => $this->makeSqliteConnection()]],
        );

        $panel = new DbPanel();

        self::assertArrayHasKey(
            'db-explain',
            $panel->actions,
            "init() must register the 'db-explain' action.",
        );
    }

    public function testIsEnabledReturnsFalseWhenDbCannotBeResolved(): void
    {
        $this->mockWebApplication();

        $panel = new DbPanel();

        $panel->db = 'missing';

        self::assertFalse(
            $panel->isEnabled(),
            'Panel must disable itself when the DB component cannot be resolved.',
        );
    }

    public function testIsEnabledReturnsTrueWhenDbResolves(): void
    {
        $this->mockWebApplication(
            ['components' => ['db' => $this->makeSqliteConnection()]],
        );

        $panel = new DbPanel();

        self::assertTrue(
            $panel->isEnabled(),
            'Panel must enable itself when the DB component resolves.',
        );
    }

    public function testIsQueryCountCriticalRespectsThreshold(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        self::assertFalse(
            $panel->isQueryCountCritical(1000),
            "'null' threshold must never flag a query count as critical.",
        );

        $panel->criticalQueryThreshold = 10;

        self::assertFalse(
            $panel->isQueryCountCritical(10),
            'Count equal to threshold must not be flagged.',
        );
        self::assertTrue(
            $panel->isQueryCountCritical(11),
            'Count above threshold must be flagged.',
        );
    }

    public function testSnapshotSerializesRowsToArrays(): void
    {
        $row = $this->makeRow();

        self::assertSame(
            ['entries' => [$row->jsonSerialize()]],
            (new DbSnapshot([$row]))->jsonSerialize(),
            'Database snapshots must serialize typed rows into JSON-safe arrays.',
        );
    }

    public function testSumDuplicateQueriesCountsRowsWithDuplicateGreaterThanOne(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        $rows = [
            $this->makeRow(duplicate: 1),
            $this->makeRow(duplicate: 2),
            $this->makeRow(duplicate: 5),
            $this->makeRow(duplicate: 1),
        ];

        self::assertSame(
            2,
            $panel->sumDuplicateQueries($rows),
            "Only rows with 'duplicate > 1' must be counted.",
        );
    }

    public function testThrowInvalidConfigExceptionWhenDbComponentIsMissing(): void
    {
        $this->mockWebApplication();

        $panel = new DbPanel();

        $panel->db = 'missing-db';

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'Unknown component ID: missing-db',
        );

        $panel->getDb();
    }

    public function testThrowInvalidConfigExceptionWhenDbComponentIsNotConnection(): void
    {
        $this->mockWebApplication(
            [
                'components' => ['db' => ['class' => Component::class]],
            ],
        );

        $panel = new DbPanel();

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            "Application component 'db' must be a DB connection.",
        );

        $panel->getDb();
    }

    /**
     * @return list<list<mixed>>
     */
    private function fakeMessages(int $count): array
    {
        $pairs = [];

        for ($i = 0; $i < $count; $i++) {
            $pairs[] = $this->makeMessage("SELECT {$i}", 0.001 * ($i + 1), 0.001 * $i);
        }

        return $this->flatten($pairs);
    }

    /**
     * Returns the first toolbar item produced by the panel as a typed array, narrowing the `mixed` return of
     * {@see TestCase::invoke()}.
     *
     * @return array<string, mixed>
     */
    private function firstToolbarItem(DbPanel $panel): array
    {
        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Must produce an array.',
        );

        $first = $items[0] ?? null;

        self::assertIsArray(
            $first,
            'Toolbar item must be an array.',
        );

        $out = [];

        foreach ($first as $key => $value) {
            if (is_string($key)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * Spreads a list of begin/end pairs (each from {@see makeMessage()}) into a flat profile-log list.
     *
     * @param list<list<list<mixed>>> $pairs
     *
     * @return list<list<mixed>>
     */
    private function flatten(array $pairs): array
    {
        $out = [];

        foreach ($pairs as $pair) {
            foreach ($pair as $entry) {
                $out[] = $entry;
            }
        }

        return $out;
    }

    /**
     * Captures the given live sources through the module's Database collector and hydrates the panel from the result.
     *
     * @param array<int, array<int|string, mixed>> $messages Raw profile tuples.
     * @param array<int, int> $rowCounts Row counts reported by the driver, in execution order.
     */
    private function hydrateFromLive(DbPanel $panel, array $messages, array $rowCounts): void
    {
        $module = $panel->module ?? self::fail('Module must be wired.');
        $coordinator = $module->getCollectorCoordinator();
        $collector = $coordinator->collector('db');

        self::assertInstanceOf(DbCollector::class, $collector, 'Db collector must be registered.');

        $coordinator->startup();

        $logTarget = $module->logTarget;

        self::assertInstanceOf(LogTarget::class, $logTarget, 'Log target must be wired.');

        $logTarget->messages = $messages;
        DebugPdoStatement::$rowCounts = $rowCounts;

        $snapshot = $collector->capture();

        $coordinator->shutdown();

        self::assertNotNull($snapshot, 'Started collector must capture a snapshot.');

        $this->hydratePanel($panel, $snapshot);
    }

    /**
     * Returns the begin+end profile-log pair Yii's logger emits for prepared statements, ready to be spread into a
     * messages list with `...`.
     *
     * @param list<array<string, mixed>> $trace
     *
     * @return list<list<mixed>>
     */
    private function makeMessage(
        string $sql,
        float $duration,
        float $startTime,
        array $trace = [],
    ): array {
        return [
            [$sql, Logger::LEVEL_PROFILE_BEGIN, 'yii\db\Command::query', $startTime, $trace, 0],
            [$sql, Logger::LEVEL_PROFILE_END, 'yii\db\Command::query', $startTime + $duration, $trace, 0],
        ];
    }

    private function makeRow(int $duplicate = 1): QueryRow
    {
        return new QueryRow(
            type: 'SELECT',
            query: 'SELECT 1',
            duration: 0.0,
            trace: [],
            traceHash: 'h',
            timestamp: 0.0,
            seq: 0,
            duplicate: $duplicate,
            rows: null,
        );
    }

    private function makeRowWithDuration(float $duration): QueryRow
    {
        return new QueryRow(
            type: 'SELECT',
            query: 'SELECT 1',
            duration: $duration,
            trace: [],
            traceHash: 'h',
            timestamp: 0.0,
            seq: 0,
            duplicate: 1,
            rows: null,
        );
    }

    private function makeSqliteConnection(): Connection
    {
        return new Connection(['dsn' => 'sqlite::memory:']);
    }

    /**
     * Sets the excessive-caller threshold on the module's Database collector.
     */
    private function setDbCollectorThreshold(DbPanel $panel, int $threshold): void
    {
        $collector = $panel->module?->getCollectorCoordinator()->collector('db');

        self::assertInstanceOf(DbCollector::class, $collector, 'Db collector must be registered.');

        $collector->excessiveCallerThreshold = $threshold;
    }
}
