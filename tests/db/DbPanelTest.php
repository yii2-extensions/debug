<?php

declare(strict_types=1);

namespace yii\debug\tests\db;

use PDO;
use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\db\Connection;
use yii\debug\db\DebugPdoStatement;
use yii\debug\LogTarget;
use yii\debug\panels\db\QueryRow;
use yii\debug\panels\DbPanel;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

use function is_string;

/**
 * Unit tests for {@see DbPanel} covering query timing aggregation, EXPLAIN gating, threshold checks, the SQL command
 * verb extractor, the badge variant mapping, toolbar/summary rendering, and snapshot hydration.
 */
#[Group('panel')]
#[Group('db')]
final class DbPanelTest extends TestCase
{
    public function testCalculateTimingsCachesNormalizedTimings(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        $this->primeDbPanel($panel, $this->fakeMessages(2), []);

        $first = $panel->calculateTimings();

        self::assertCount(
            2,
            $first,
            'Captured messages must yield two timings.',
        );
        self::assertSame(
            $first,
            $panel->calculateTimings(),
            'Second call must return the cached list.',
        );
    }

    public function testCalculateTimingsSkipsRawTimingsThatNormalizeToNull(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        $this->primeDbPanel($panel, [
            [['non', 'string', 'token'], Logger::LEVEL_PROFILE_BEGIN, 'cat', 0.0, [], 0],
            [['non', 'string', 'token'], Logger::LEVEL_PROFILE_END, 'cat', 0.001, [], 0],
        ], []);

        self::assertSame(
            [],
            $panel->calculateTimings(),
            "Raw timings whose 'info' cannot be coerced to a string must be dropped.",
        );
    }

    public function testCalculateTimingsSkipsTraceFramesWithNonStringFile(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        $this->primeDbPanel($panel, [
            ...$this->makeMessage(
                'SELECT 1',
                0.001,
                0.0,
                trace: [
                    ['file' => 123, 'line' => 1],
                    ['file' => '/keep/bar.php', 'line' => 2],
                ],
            ),
        ], []);
        $panel->ignoredPathsInBacktrace = ['/x'];

        $timings = $panel->calculateTimings();

        $first = $timings[0] ?? self::fail('Expected one timing.');

        self::assertCount(
            2,
            $first['trace'],
            'Frames with a non-string file must be left untouched.',
        );
    }

    public function testCalculateTimingsSkipsTracesUnderIgnoredPaths(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        $this->primeDbPanel($panel, [
            ...$this->makeMessage(
                'SELECT 1',
                0.001,
                0.0,
                trace: [
                    ['file' => '/tmp/ignored/foo.php', 'line' => 1],
                    ['file' => '/tmp/kept/bar.php', 'line' => 2],
                ],
            ),
        ], []);
        $panel->ignoredPathsInBacktrace = ['/tmp/ignored'];

        $timings = $panel->calculateTimings();

        self::assertCount(
            1,
            $timings,
            'One timing must remain.',
        );

        $first = $timings[0] ?? self::fail('Expected one timing.');

        self::assertCount(
            1,
            $first['trace'],
            'Ignored-path frame must be dropped from the trace.',
        );
    }

    public function testCanBeExplainedReturnsFalseForUnsupportedVerb(): void
    {
        self::assertFalse(
            DbPanel::canBeExplained('PRAGMA'),
            'PRAGMA must not be marked as EXPLAIN-able.',
        );
        self::assertFalse(
            DbPanel::canBeExplained(''),
            'Empty verb must not be marked as EXPLAIN-able.',
        );
    }

    public function testCanBeExplainedReturnsTrueForSupportedVerbs(): void
    {
        foreach (['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'WITH'] as $verb) {
            self::assertTrue(
                DbPanel::canBeExplained($verb),
                "Verb '{$verb}' must be EXPLAIN-able.",
            );
            self::assertTrue(
                DbPanel::canBeExplained(strtolower($verb)),
                "Verb '{$verb}' must be EXPLAIN-able regardless of case.",
            );
        }
    }

    public function testCaptureReturnsNoRowsWithoutProfileLogs(): void
    {
        DebugPdoStatement::$rowCounts = [3, 7];

        $panel = $this->makePanel(DbPanel::class);

        self::assertSame([], $panel->capture()->entries(), 'An empty profile log yields no query rows.');

        DebugPdoStatement::$rowCounts = [];
    }

    public function testCountCallerCalsGroupsByTraceHash(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        $this->primeDbPanel($panel, $this->fakeMessages(3), []);

        $counts = $panel->countCallerCals();

        self::assertNotEmpty(
            $counts,
            'Caller counts must reflect captured timings.',
        );
        self::assertSame(
            3,
            array_sum($counts),
            'Total caller calls must match the message count.',
        );
    }

    public function testCountDuplicateQueryCountsRepeatedSqlStatements(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        $timings = [
            $this->makeTiming('SELECT 1'),
            $this->makeTiming('SELECT 1'),
            $this->makeTiming('SELECT 2'),
        ];

        $counts = $panel->countDuplicateQuery($timings);

        self::assertSame(
            ['SELECT 1' => 2, 'SELECT 2' => 1],
            $counts,
            'Duplicate counts must group identical SQL statements.',
        );
    }

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

        $this->primeDbPanel($panel, [], []);

        self::assertStringNotContainsString(
            'Explain all',
            $panel->getDetail(),
            'Explain toggle must not render without queries.',
        );
    }

    public function testGetDetailRendersDuplicatedCounterWhenSameQueryRepeats(): void
    {
        $panel = $this->makePanel(DbPanel::class, ['db' => $this->makeSqliteConnection()]);

        $this->primeDbPanel($panel, [
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

        $this->primeDbPanel($panel, [], []);

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

        $this->primeDbPanel($panel, [...$this->makeMessage('SELECT 1', 0.001, 0.0)], []);

        $html = $panel->getDetail();

        self::assertNotEmpty(
            $html,
            'Detail view must produce non-empty markup.',
        );
    }

    public function testGetExcessiveCallersReturnsCallersAtOrAboveThreshold(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        $this->primeDbPanel($panel, $this->fakeMessages(3), []);

        $panel->excessiveCallerThreshold = 2;

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

        $this->primeDbPanel($panel, $this->fakeMessages(5), []);

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

    public function testGetModelsAssemblesTimingsWithMillisecondScaling(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        $this->primeDbPanel($panel, [...$this->makeMessage('SELECT * FROM t', 0.005, 0.010)], [42]);

        $models = $this->invoke($panel, 'getModels');

        self::assertIsArray($models, 'Must produce a list of rows.');

        $row = $models[0] ?? self::fail('Expected one row.');

        self::assertInstanceOf(QueryRow::class, $row, 'Rows must be typed.');
        self::assertSame(
            'SELECT',
            $row->type,
            'Verb must be uppercased.',
        );
        self::assertEqualsWithDelta(
            5.0,
            $row->duration,
            1e-9,
            'Duration must be scaled to milliseconds.',
        );
        self::assertEqualsWithDelta(
            10.0,
            $row->timestamp,
            1e-9,
            'Timestamp must be scaled to milliseconds.',
        );
        self::assertSame(
            42,
            $row->rows,
            'Saved row count must round-trip.',
        );
    }

    public function testGetModelsResolvesStableRows(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        $this->primeDbPanel($panel, [...$this->makeMessage('SELECT 1', 0.001, 0.0)], []);

        self::assertEquals(
            $this->invoke($panel, 'getModels'),
            $this->invoke($panel, 'getModels'),
            'Repeated reads must resolve the same rows.',
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

    public function testGetProfileLogsCachesResult(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        $first = $panel->getProfileLogs();
        $second = $panel->getProfileLogs();

        self::assertSame(
            $first,
            $second,
            'Must return the cached list on subsequent calls.',
        );
    }

    public function testGetQueryTypeExtractsLeadingVerb(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        self::assertSame(
            'SELECT',
            $this->invoke(
                $panel,
                'getQueryType',
                ['select * from t'],
            ),
            'Lowercase verb must be upcased.',
        );
        self::assertSame(
            'INSERT',
            $this->invoke(
                $panel,
                'getQueryType',
                ['  INSERT INTO t VALUES (1)'],
            ),
            'Leading whitespace must be trimmed.',
        );
        self::assertSame(
            '',
            $this->invoke(
                $panel,
                'getQueryType',
                ['123 not sql'],
            ),
            'Non-letter prefix must yield an empty verb.',
        );
    }

    public function testGetToolbarItemsEmitsWarningForExcessiveCallers(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        $this->primeDbPanel($panel, [
            ...$this->makeMessage('SELECT 1', 0.001, 0.0, trace: [['file' => '/a.php', 'line' => 1]]),
            ...$this->makeMessage('SELECT 2', 0.001, 0.001, trace: [['file' => '/b.php', 'line' => 1]]),
        ], []);
        $panel->excessiveCallerThreshold = 0;

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

        $this->primeDbPanel($panel, [...$this->makeMessage('SELECT 1', 0.001, 0.0)], []);
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

        $this->primeDbPanel($panel, [...$this->makeMessage('SELECT 1', 0.001, 0.0)], []);
        $panel->excessiveCallerThreshold = 0;

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

        $this->primeDbPanel($panel, [
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

    public function testInitAppliesStatementClassOnAfterOpenEvent(): void
    {
        $db = $this->makeSqliteConnection();

        $this->mockWebApplication(
            ['components' => ['db' => $db]],
        );

        $panel = new DbPanel();

        $db->open();

        self::assertNotNull(
            $db->pdo,
            'PDO must be open.',
        );
        self::assertSame(
            [DebugPdoStatement::class, []],
            $db->pdo->getAttribute(PDO::ATTR_STATEMENT_CLASS),
            'PDO statement class must be set on connection opening after init.',
        );

        unset($panel);
    }

    public function testInitAppliesStatementClassToAlreadyOpenedConnection(): void
    {
        $db = $this->makeSqliteConnection();

        $db->open();

        $this->mockWebApplication(
            ['components' => ['db' => $db]],
        );

        $panel = new DbPanel();

        self::assertNotNull(
            $db->pdo,
            'PDO must be open.',
        );
        self::assertSame(
            [DebugPdoStatement::class, []],
            $db->pdo->getAttribute(PDO::ATTR_STATEMENT_CLASS),
            'PDO statement class must be set on a pre-opened connection.',
        );

        unset($panel);
    }

    public function testInitIsAnoopWhenDbComponentIsMissing(): void
    {
        $this->mockWebApplication();

        $panel = new DbPanel();

        $panel->db = 'absent';

        $panel->init();

        self::assertArrayHasKey(
            'db-explain',
            $panel->actions,
            "Init must always register the 'db-explain' action.",
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

    public function testNormalizeTimingFillsDefaultsForOptionalFields(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        $normalized = $this->invoke(
            $panel,
            'normalizeTiming',
            [
                [
                    'info' => 'SELECT 1',
                    'timestamp' => 0.5,
                    'duration' => 0.001,
                ],
            ],
        );

        self::assertIsArray(
            $normalized,
            'Valid raw timing must produce an array.',
        );
        self::assertSame(
            'SELECT 1',
            $normalized['info'] ?? null,
            "'info' must round-trip.",
        );
        self::assertSame(
            '',
            $normalized['category'] ?? null,
            "Missing 'category' must collapse to ''.",
        );
        self::assertSame(
            0,
            $normalized['level'] ?? null,
            "Missing 'level' must collapse to '0'.",
        );
    }

    public function testNormalizeTimingReturnsNullForIncompletePayloads(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        self::assertNull(
            $this->invoke(
                $panel,
                'normalizeTiming',
                ['not an array'],
            ),
            "Non-array raw timing must yield 'null'.",
        );
        self::assertNull(
            $this->invoke(
                $panel,
                'normalizeTiming',
                [
                    [
                        'info' => null,
                        'timestamp' => 0.0,
                        'duration' => 0.0,
                    ],
                ],
            ),
            "Missing 'info' must yield 'null'.",
        );
        self::assertNull(
            $this->invoke(
                $panel,
                'normalizeTiming',
                [
                    [
                        'info' => 'x',
                        'timestamp' => null,
                        'duration' => 0.0,
                    ],
                ],
            ),
            "Missing 'timestamp' must yield 'null'.",
        );
        self::assertNull(
            $this->invoke(
                $panel,
                'normalizeTiming',
                [
                    [
                        'info' => 'x',
                        'timestamp' => 0.0,
                        'duration' => null,
                    ],
                ],
            ),
            "Missing 'duration' must yield 'null'.",
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

    public function testTraceHashAlgoIsCachedAcrossCalls(): void
    {
        $panel = $this->makePanel(DbPanel::class);

        $first = $this->invoke(
            $panel,
            'traceHashAlgo',
        );
        $second = $this->invoke(
            $panel,
            'traceHashAlgo',
        );

        self::assertSame(
            $first,
            $second,
            'traceHashAlgo must return the same algorithm across calls.',
        );
        self::assertContains(
            $first,
            ['xxh3', 'crc32'],
            'Algorithm must be one of the two candidates.',
        );
    }

    public function testTypeBadgeVariantMapsVerbsToVocabularySuffixes(): void
    {
        $mappings = [
            'SELECT' => 'get', 'SHOW' => 'get', 'EXPLAIN' => 'get', 'DESCRIBE' => 'get', 'PRAGMA' => 'get',
            'INSERT' => 'post',
            'UPDATE' => 'put', 'REPLACE' => 'put', 'UPSERT' => 'put',
            'DELETE' => 'delete', 'DROP' => 'delete', 'TRUNCATE' => 'delete',
            'BOGUS' => 'other', '' => 'other',
        ];

        foreach ($mappings as $verb => $expected) {
            self::assertSame(
                $expected,
                DbPanel::typeBadgeVariant($verb),
                "Verb '{$verb}' must map to '{$expected}'.",
            );
        }
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
     * @return array{
     *   info: string, category: string, timestamp: float, trace: array<int, array<string, mixed>>,
     *   level: int, duration: float, memory: int, memoryDiff: int, traceHash: string
     * }
     */
    private function makeTiming(string $info, float $duration = 0.0): array
    {
        return [
            'info' => $info,
            'category' => '',
            'timestamp' => 0.0,
            'trace' => [],
            'level' => 0,
            'duration' => $duration,
            'memory' => 0,
            'memoryDiff' => 0,
            'traceHash' => '',
        ];
    }

    /**
     * Primes the panel's live sources so the pre-capture path resolves the given queries.
     *
     * @param array<int, array<int|string, mixed>> $messages Raw profile tuples.
     * @param array<int, int> $rowCounts Row counts reported by the driver, in execution order.
     */
    private function primeDbPanel(DbPanel $panel, array $messages, array $rowCounts): void
    {
        $module = $panel->module ?? self::fail('Module must be wired.');
        $logTarget = $module->logTarget;

        self::assertInstanceOf(LogTarget::class, $logTarget, 'Log target must be wired.');

        $logTarget->messages = $messages;
        DebugPdoStatement::$rowCounts = $rowCounts;
    }
}
