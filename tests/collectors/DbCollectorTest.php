<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use Closure;
use PDO;
use PHPForge\Debug\Panel\Db\QueryRow;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use Yii;
use yii\db\Connection;
use yii\debug\collectors\DbCollector;
use yii\debug\db\DebugPdoStatement;
use yii\debug\{LogTarget, Module};
use yii\debug\tests\provider\VisibilityProvider;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

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

    public function testShutdownDetachesAfterOpenListener(): void
    {
        $db = $this->makeSqliteConnection();

        $this->mockWebApplication(['components' => ['db' => $db]]);

        $collector = new DbCollector();

        $collector->startup();

        $listener = $this->getInaccessibleProperty($collector, 'afterOpenListener');
        $events = $this->getInaccessibleProperty($db, '_events');

        self::assertInstanceOf(
            Closure::class,
            $listener,
            'Startup must retain the DB listener.',
        );
        self::assertIsArray(
            $events,
            'Connection events must be stored as an array.',
        );

        $afterOpenEvents = $events[Connection::EVENT_AFTER_OPEN] ?? null;

        self::assertIsArray(
            $afterOpenEvents,
            'The after-open handler list must be stored.',
        );

        $firstEvent = $afterOpenEvents[0] ?? null;

        self::assertIsArray(
            $firstEvent,
            'The first after-open handler must be stored.',
        );
        self::assertSame(
            $listener,
            $firstEvent[0] ?? null,
            'Startup must attach the DB listener.',
        );

        $collector->shutdown();

        $events = $this->getInaccessibleProperty($db, '_events');

        self::assertIsArray(
            $events,
            'Connection events must remain an array after shutdown.',
        );
        self::assertSame(
            [],
            $events[Connection::EVENT_AFTER_OPEN] ?? [],
            'Shutdown must detach the DB listener.',
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

    public function testStartIsANoopWhenDbComponentIsMissing(): void
    {
        $this->mockWebApplication();

        $collector = new DbCollector();

        $collector->db = 'absent';

        $collector->startup();

        $collector->shutdown();

        self::assertNull(
            $collector->capture(),
            'Stopped collector must record nothing.',
        );
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
     * @param list<int> $rowCounts Row counts reported by the driver, in execution order.
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
}
