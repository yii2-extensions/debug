<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use LogicException;
use PDO;
use PHPForge\Debug\Panel\Db\QueryRow;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use Yii;
use yii\base\Event;
use yii\db\Connection;
use yii\debug\collectors\DbCollector;
use yii\debug\db\DebugPdoStatement;
use yii\debug\{LogTarget, Module};
use yii\debug\tests\provider\VisibilityProvider;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

use function array_map;
use function hash_algos;
use function in_array;

/**
 * Unit tests for {@see DbCollector} covering query timing aggregation, the SQL command verb extractor, the PDO
 * statement hook lifecycle, and the trace-hash fingerprinting.
 *
 * {@see VisibilityProvider} for method contract data providers.
 *
 * @phpstan-import-type LogTrace from Logger
 * @phpstan-type StringLogMessage array{0: string, 1: int, 2: string, 3: float, 4: list<LogTrace>, 5: int}
 */
#[Group('collector')]
#[Group('db')]
final class DbCollectorTest extends TestCase
{
    public function testCalculateTimingsCachesNormalizedTimings(): void
    {
        $collector = $this->makeCollector();

        $this->primeCollector(
            $collector,
            $this->fakeMessages(2),
            [],
        );

        $first = $collector->calculateTimings();

        self::assertCount(
            2,
            $first,
            'Captured messages must yield two timings.',
        );
        self::assertSame(
            $first,
            $collector->calculateTimings(),
            'Second call must return the cached list.',
        );
    }

    public function testCalculateTimingsKeepsTraceFramesWithoutStringFile(): void
    {
        $collector = $this->makeCollector();

        $this->setInaccessibleProperty(
            $collector,
            'profileLogs',
            [
                [
                    'SELECT 1',
                    Logger::LEVEL_PROFILE_BEGIN,
                    'yii\db\Command::query',
                    0.0,
                    [
                        ['line' => 1],
                        ['file' => '/tmp/ignored/query.php', 'line' => 2],
                    ],
                    0,
                ],
                [
                    'SELECT 1',
                    Logger::LEVEL_PROFILE_END,
                    'yii\db\Command::query',
                    0.001,
                    [
                        ['line' => 1],
                        ['file' => '/tmp/ignored/query.php', 'line' => 2],
                    ],
                    0,
                ],
            ],
        );

        $collector->ignoredPathsInBacktrace = ['/tmp/ignored'];

        $timings = $collector->calculateTimings();

        $first = $timings[0] ?? self::fail('Expected one timing.');

        self::assertSame(
            [['line' => 1]],
            $first['trace'],
            'Frames without a string file must remain while matching file paths are removed.',
        );
    }

    public function testCalculateTimingsSkipsTracesUnderIgnoredPaths(): void
    {
        $collector = $this->makeCollector();

        $this->primeCollector(
            $collector,
            [
                ...$this->makeMessage(
                    'SELECT 1',
                    0.001,
                    0.0,
                    trace: [
                        ['file' => '/tmp/ignored/foo.php', 'line' => 1],
                        ['file' => '/tmp/kept/bar.php', 'line' => 2],
                    ],
                ),
            ],
            [],
        );

        Yii::setAlias('@ignored', '/tmp/ignored');

        $collector->ignoredPathsInBacktrace = ['@ignored'];

        $timings = $collector->calculateTimings();

        self::assertCount(
            1,
            $timings,
            'One timing must remain.',
        );

        $first = $timings[0] ?? self::fail('Expected one timing.');

        self::assertSame(
            [['file' => '/tmp/kept/bar.php', 'line' => 2]],
            $first['trace'],
            'Ignored-path frames must be dropped and the remaining trace reindexed.',
        );
    }

    public function testCaptureAlignsRowCountsAcrossEveryProfiledConnection(): void
    {
        $db = $this->makeSqliteConnection();
        $other = $this->makeSqliteConnection();

        $collector = $this->makeCollector(['db' => $db, 'db2' => $other]);

        $logger = Yii::getLogger();

        $logger->messages = [];

        $db->createCommand('CREATE TABLE first (id INTEGER PRIMARY KEY)')->execute();
        $other->createCommand('CREATE TABLE second (id INTEGER PRIMARY KEY)')->execute();
        $db->createCommand('INSERT INTO first (id) VALUES (1)')->execute();
        $other->createCommand('INSERT INTO second (id) VALUES (1), (2)')->execute();
        $db->createCommand('INSERT INTO first (id) VALUES (2), (3), (4)')->execute();

        $logTarget = $collector->module?->logTarget;

        self::assertInstanceOf(
            LogTarget::class,
            $logTarget,
            'Log target must be wired.',
        );

        $logTarget->collect($logger->messages, false);

        self::assertSame(
            [0, 0, 1, 2, 3],
            $this->rowsOf($this->captureEntries($collector)),
            'Every profiled connection must contribute its own counts, in execution order.',
        );

        $collector->shutdown();
    }

    public function testCaptureAlignsRowCountsWithTheTrailingTimings(): void
    {
        $collector = $this->makeCollector();

        $this->primeCollector(
            $collector,
            $this->fakeMessages(5),
            [5, 7],
        );

        self::assertSame(
            [null, null, null, 5, 7],
            $this->rowsOf($this->captureEntries($collector)),
            'Counts must land on the last timings.',
        );
    }

    public function testCaptureAssemblesTimingsWithMillisecondScaling(): void
    {
        $collector = $this->makeCollector();

        $this->primeCollector(
            $collector,
            [...$this->makeMessage('SELECT * FROM t', 0.005, 0.010)],
            [0],
        );

        $models = $this->captureEntries($collector);

        $row = $models[0] ?? self::fail('Expected one row.');

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
            0,
            $row->rows,
            'A zero row count must remain a valid driver result.',
        );
    }

    public function testCaptureKeepsAlignmentWhenAnExecutionRecordedNoRowCount(): void
    {
        $collector = $this->makeCollector();

        $this->primeCollector(
            $collector,
            $this->fakeMessages(4),
            [3, null, 8],
        );

        self::assertSame(
            [null, 3, null, 8],
            $this->rowsOf($this->captureEntries($collector)),
            'A failed execution must keep its slot.',
        );
    }

    public function testCaptureRejectsMissingDuplicateCountInvariant(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug');

        $module->logTarget = new LogTarget($module);

        $collector = $this->getMockBuilder(DbCollector::class)
            ->onlyMethods(['countDuplicateQuery'])
            ->getMock();

        $collector->expects(self::once())->method('countDuplicateQuery')->willReturn([]);
        $collector->module = $module;
        $collector->startup();

        $this->primeCollector(
            $collector,
            [...$this->makeMessage('SELECT 1', 0.001, 0.0)],
            [],
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Missing duplicate count for query: SELECT 1');

        $collector->capture();
    }

    public function testCaptureResolvesStableRows(): void
    {
        $collector = $this->makeCollector();

        $this->primeCollector(
            $collector,
            [...$this->makeMessage('SELECT 1', 0.001, 0.0)],
            [],
        );

        self::assertEquals(
            $this->captureEntries($collector),
            $this->captureEntries($collector),
            'Repeated reads must resolve the same rows.',
        );
    }

    public function testCaptureReturnsNoRowsWithoutProfileLogs(): void
    {
        DebugPdoStatement::$rowCounts = [3, 7];

        $collector = $this->makeCollector();

        self::assertSame(
            [],
            $this->captureEntries($collector),
            'An empty profile log yields no query rows.',
        );

        DebugPdoStatement::$rowCounts = [];
    }

    public function testCaptureReturnsNullBeforeStartup(): void
    {
        $this->mockWebApplication();

        self::assertNull(
            (new DbCollector())->capture(),
            'Idle collector must record nothing.',
        );
    }

    public function testCaptureReturnsNullRowsWhenCountsOutnumberTimings(): void
    {
        $collector = $this->makeCollector();

        $this->primeCollector(
            $collector,
            $this->fakeMessages(1),
            [5, 7],
        );

        self::assertSame(
            [null],
            $this->rowsOf($this->captureEntries($collector)),
            'An unalignable list must yield no counts.',
        );
    }

    public function testCountCallerCalsGroupsByTraceHash(): void
    {
        $collector = $this->makeCollector();

        $this->primeCollector(
            $collector,
            $this->fakeMessages(3),
            [],
        );

        $counts = $collector->countCallerCals();

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

    public function testCountCallerCalsKeepsDistinctTraceHashes(): void
    {
        $collector = $this->makeCollector();

        $this->primeCollector(
            $collector,
            [
                ...$this->makeMessage('SELECT 1', 0.001, 0.0, [['file' => '/one.php', 'line' => 1]]),
                ...$this->makeMessage('SELECT 2', 0.001, 0.1, [['file' => '/two.php', 'line' => 2]]),
            ],
            [],
        );

        self::assertSame(
            [1, 1],
            array_values($collector->countCallerCals()),
            'Distinct traces must retain both caller-count buckets.',
        );
    }

    public function testCountDuplicateQueryCountsRepeatedSqlStatements(): void
    {
        $collector = $this->makeCollector();

        $timings = [
            $this->makeTiming('SELECT 1'),
            $this->makeTiming('SELECT 1'),
            $this->makeTiming('SELECT 2'),
        ];

        $counts = $collector->countDuplicateQuery($timings);

        self::assertSame(
            ['SELECT 1' => 2, 'SELECT 2' => 1],
            $counts,
            'Duplicate counts must group identical SQL statements.',
        );
    }

    /**
     * @param class-string $class
     * @param 'protected'|'public' $expected
     */
    #[DataProviderExternal(VisibilityProvider::class, 'dbCollectorContracts')]
    public function testExtensionMethodKeepsDeclaredVisibility(string $class, string $method, string $expected): void
    {
        self::assertMethodVisibility($class, $method, $expected);
    }

    public function testGetExcessiveCallersReturnsEmptyWhenDisabledWithCapturedRows(): void
    {
        $collector = $this->makeCollector();

        $this->primeCollector(
            $collector,
            $this->fakeMessages(1),
            [],
        );

        self::assertSame(
            [],
            $collector->getExcessiveCallers(),
            'A null threshold must disable caller detection.',
        );
    }

    public function testGetExcessiveCallersReturnsOnlyCountsAtOrAboveThreshold(): void
    {
        $collector = $this->makeCollector();

        $collector->excessiveCallerThreshold = 2;

        $repeatedTrace = [['file' => '/app/Repository.php', 'line' => 42]];

        $this->primeCollector(
            $collector,
            $this->flatten(
                [
                    $this->makeMessage('SELECT 1', 0.001, 0.000, $repeatedTrace),
                    $this->makeMessage('SELECT 2', 0.001, 0.002, $repeatedTrace),
                    $this->makeMessage(
                        'SELECT 3',
                        0.001,
                        0.004,
                        [['file' => '/app/OtherRepository.php', 'line' => 7]],
                    ),
                ],
            ),
            [],
        );

        self::assertSame(
            [2],
            array_values($collector->getExcessiveCallers()),
            'Only callers reaching the inclusive threshold must be reported.',
        );
    }

    public function testGetProfileLogsCachesResult(): void
    {
        $collector = $this->makeCollector();

        $first = $collector->getProfileLogs();
        $second = $collector->getProfileLogs();

        self::assertSame(
            $first,
            $second,
            'Must return the cached list on subsequent calls.',
        );
    }

    public function testGetQueryTypeExtractsLeadingVerb(): void
    {
        $collector = $this->makeCollector();

        self::assertSame(
            'SELECT',
            $this->invoke(
                $collector,
                'getQueryType',
                ['select * from t'],
            ),
            'Lowercase verb must be upcased.',
        );
        self::assertSame(
            'INSERT',
            $this->invoke(
                $collector,
                'getQueryType',
                ['  INSERT INTO t VALUES (1)'],
            ),
            'Leading whitespace must be trimmed.',
        );
        self::assertSame(
            '',
            $this->invoke(
                $collector,
                'getQueryType',
                ['123 not sql'],
            ),
            'Non-letter prefix must yield an empty verb.',
        );
    }

    public function testIdPairsWithTheDatabasePanel(): void
    {
        self::assertSame(
            'db',
            (new DbCollector())->id(),
            "Stable ID must be 'db'.",
        );
    }

    public function testInstrumentInstallsTheStatementHookOnlyOnce(): void
    {
        $db = $this->makeSqliteConnection();

        $this->mockWebApplication(['components' => ['db' => $db]]);

        // Debug modules built earlier in the process may still listen for opening connections; start from a clean
        // registration so the listener under test is the only one left.
        Event::off(Connection::class, Connection::EVENT_AFTER_OPEN);

        $collector = new DbCollector();

        DebugPdoStatement::$rowCounts = [1, 2];

        $collector->instrument();

        self::assertSame(
            [],
            DebugPdoStatement::$rowCounts,
            'The first installation must discard stale counts.',
        );

        DebugPdoStatement::$rowCounts = [5];

        $collector->instrument();

        self::assertSame(
            [5],
            DebugPdoStatement::$rowCounts,
            'A repeated installation must keep the counts.',
        );

        $collector->startup();
        $collector->shutdown();

        $late = $this->makeSqliteConnection();

        $late->open();

        self::assertNotNull(
            $late->pdo,
            'PDO must be open.',
        );
        self::assertNotSame(
            [DebugPdoStatement::class, []],
            $late->pdo->getAttribute(PDO::ATTR_STATEMENT_CLASS),
            'Shutdown must leave no duplicate listener behind.',
        );

        DebugPdoStatement::$rowCounts = [];
    }

    public function testModuleInstrumentsTheCollectorWhileInitializing(): void
    {
        $db = $this->makeSqliteConnection();

        $db->open();

        $this->mockWebApplication(['components' => ['db' => $db]]);

        new Module('debug');

        self::assertNotNull(
            $db->pdo,
            'PDO must be open.',
        );
        self::assertSame(
            [DebugPdoStatement::class, []],
            $db->pdo->getAttribute(PDO::ATTR_STATEMENT_CLASS),
            'Bootstrap must install the statement class ahead of the panels.',
        );
    }

    public function testShutdownDetachesAfterOpenListener(): void
    {
        $db = $this->makeSqliteConnection();

        $this->mockWebApplication(['components' => ['db' => $db]]);

        // Debug modules built earlier in the process may still listen for opening connections; start from a clean
        // registration so the listener under test is the only one left.
        Event::off(Connection::class, Connection::EVENT_AFTER_OPEN);

        $collector = new DbCollector();

        $collector->startup();
        $collector->shutdown();

        $late = $this->makeSqliteConnection();

        $late->open();

        self::assertNotNull(
            $late->pdo,
            'PDO must be open.',
        );
        self::assertNotSame(
            [DebugPdoStatement::class, []],
            $late->pdo->getAttribute(PDO::ATTR_STATEMENT_CLASS),
            'A connection opened afterwards must keep the default statement class.',
        );
    }

    public function testStartAppliesStatementClassOnAfterOpenEvent(): void
    {
        $db = $this->makeSqliteConnection();

        $this->mockWebApplication(['components' => ['db' => $db]]);

        $collector = new DbCollector();

        $collector->startup();

        $db->open();

        self::assertNotNull(
            $db->pdo,
            'PDO must be open.',
        );
        self::assertSame(
            [DebugPdoStatement::class, []],
            $db->pdo->getAttribute(PDO::ATTR_STATEMENT_CLASS),
            'PDO statement class must be set on connection opening after startup.',
        );

        $collector->shutdown();
    }

    public function testStartAppliesStatementClassToAlreadyOpenedConnection(): void
    {
        $db = $this->makeSqliteConnection();

        $db->open();

        $this->mockWebApplication(['components' => ['db' => $db]]);

        $collector = new DbCollector();

        $collector->startup();

        self::assertNotNull(
            $db->pdo,
            'PDO must be open.',
        );
        self::assertSame(
            [DebugPdoStatement::class, []],
            $db->pdo->getAttribute(PDO::ATTR_STATEMENT_CLASS),
            'PDO statement class must be set on a pre-opened connection.',
        );

        $collector->shutdown();
    }

    public function testStartAppliesStatementClassToOpenConnectionsBuiltFromConfiguration(): void
    {
        $this->mockWebApplication(
            [
                'components' => [
                    'db' => $this->makeSqliteConnection(),
                    'db2' => ['class' => Connection::class, 'dsn' => 'sqlite::memory:'],
                ],
            ],
        );

        // Debug modules built earlier in the process may still listen for opening connections; start from a clean
        // registration so only the startup sweep can instrument the connection below.
        Event::off(Connection::class, Connection::EVENT_AFTER_OPEN);

        $other = Yii::$app->get('db2');

        self::assertInstanceOf(
            Connection::class,
            $other,
            'Configured component must build a connection.',
        );

        $other->open();

        $collector = new DbCollector();

        $collector->startup();

        self::assertNotNull(
            $other->pdo,
            'PDO must be open.',
        );
        self::assertSame(
            [DebugPdoStatement::class, []],
            $other->pdo->getAttribute(PDO::ATTR_STATEMENT_CLASS),
            'A connection built from configuration and already open must be instrumented.',
        );

        $collector->shutdown();
    }

    public function testStartDiscardsRowCountsRecordedForAnotherRequest(): void
    {
        $collector = $this->makeCollector(['db' => $this->makeSqliteConnection()]);
        $logTarget = $collector->module?->logTarget;

        self::assertInstanceOf(
            LogTarget::class,
            $logTarget,
            'Log target must be wired.',
        );

        DebugPdoStatement::$rowCounts = [4, 9];

        $collector->shutdown();

        $logTarget->beginRequest();

        $collector->startup();

        self::assertSame(
            [],
            DebugPdoStatement::$rowCounts,
            'A new request must start from an empty list.',
        );

        $collector->shutdown();
    }

    public function testStartIsANoopWhenDbComponentIsMissing(): void
    {
        $this->mockWebApplication();

        // Debug modules built earlier in the process may still listen for opening connections; start from a clean
        // registration so no foreign listener answers for the collector under test.
        Event::off(Connection::class, Connection::EVENT_AFTER_OPEN);

        $collector = new DbCollector();

        $collector->db = 'absent';

        $collector->startup();

        $late = $this->makeSqliteConnection();

        $late->open();

        self::assertNotNull(
            $late->pdo,
            'PDO must be open.',
        );
        self::assertNotSame(
            [DebugPdoStatement::class, []],
            $late->pdo->getAttribute(PDO::ATTR_STATEMENT_CLASS),
            'A missing connection must leave every connection uninstrumented.',
        );

        $collector->shutdown();

        self::assertNull(
            $collector->capture(),
            'Stopped collector must record nothing.',
        );
    }

    public function testStartKeepsRowCountsRecordedForTheSameRequest(): void
    {
        $collector = $this->makeCollector(['db' => $this->makeSqliteConnection()]);

        DebugPdoStatement::$rowCounts = [4, 9];

        // Debug modules built earlier in the process may still listen for opening connections; start from a clean
        // registration so only the restarted collector can instrument the connection below.
        Event::off(Connection::class, Connection::EVENT_AFTER_OPEN);

        $collector->shutdown();
        $collector->startup();

        self::assertSame(
            [4, 9],
            DebugPdoStatement::$rowCounts,
            'A second capture of one request must keep the counts.',
        );

        $late = $this->makeSqliteConnection();

        $late->open();

        self::assertNotNull(
            $late->pdo,
            'PDO must be open.',
        );
        self::assertSame(
            [DebugPdoStatement::class, []],
            $late->pdo->getAttribute(PDO::ATTR_STATEMENT_CLASS),
            'The hook must be reinstalled.',
        );

        $collector->shutdown();

        DebugPdoStatement::$rowCounts = [];
    }

    public function testTraceHashAlgoIsCachedAcrossCalls(): void
    {
        $collector = $this->makeCollector();

        $this->setInaccessibleStaticProperty(DbCollector::class, 'traceHashAlgo', null);

        $first = $this->invoke(
            $collector,
            'traceHashAlgo',
        );
        $second = $this->invoke(
            $collector,
            'traceHashAlgo',
        );

        self::assertSame(
            $first,
            $second,
            'Trace hashing must use the same algorithm across calls.',
        );
        self::assertSame(
            in_array('xxh3', hash_algos(), true) ? 'xxh3' : 'crc32',
            $first,
            'The preferred available algorithm must be selected.',
        );
    }

    /**
     * Captures the query rows, failing when the started collector produces no snapshot.
     *
     * @param DbCollector $collector Started collector.
     *
     * @return list<QueryRow> Captured query rows.
     */
    private function captureEntries(DbCollector $collector): array
    {
        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'Started collector must capture a snapshot.',
        );

        return $snapshot->entries();
    }

    /**
     * @return list<StringLogMessage>
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
     * Spreads a list of begin/end pairs (each from {@see makeMessage()}) into a flat profile-log list.
     *
     * @param list<list<StringLogMessage>> $pairs
     *
     * @return list<StringLogMessage>
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
     * Creates a started collector wired to a debug module on top of a mocked web application.
     *
     * @param array<string, mixed> $components Extra application components.
     *
     * @return DbCollector Started collector.
     */
    private function makeCollector(array $components = []): DbCollector
    {
        $this->mockWebApplication(['components' => $components]);

        $module = new Module('debug');

        $module->logTarget = new LogTarget($module);

        $collector = new DbCollector();

        $collector->module = $module;

        $collector->startup();

        return $collector;
    }

    /**
     * Returns the begin+end profile-log pair Yii's logger emits for prepared statements, ready to be spread into a
     * messages list with `...`.
     *
     * @param list<LogTrace> $trace
     *
     * @return list<StringLogMessage>
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
     * Primes the collector's live sources so the capture path resolves the given queries.
     *
     * @param list<StringLogMessage> $messages Raw profile tuples.
     * @param list<int|null> $rowCounts Row counts reported by the driver, in execution order.
     */
    private function primeCollector(DbCollector $collector, array $messages, array $rowCounts): void
    {
        $module = $collector->module ?? self::fail('Module must be wired.');

        $logTarget = $module->logTarget;

        self::assertInstanceOf(
            LogTarget::class,
            $logTarget,
            'Log target must be wired.',
        );

        $logTarget->messages = $messages;

        DebugPdoStatement::$rowCounts = $rowCounts;
    }

    /**
     * @param list<QueryRow> $rows Captured query rows.
     *
     * @return list<int|null> Row count of each captured row, in capture order.
     */
    private function rowsOf(array $rows): array
    {
        return array_map(static fn(QueryRow $row): int|null => $row->rows, $rows);
    }
}
