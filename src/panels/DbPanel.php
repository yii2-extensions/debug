<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PDO;
use Yii;
use yii\base\{Event, InvalidConfigException};
use yii\data\Sort;
use yii\db\Connection;
use yii\debug\actions\db\ExplainAction;
use yii\debug\db\DebugPdoStatement;
use yii\debug\helpers\{Coerce, Vocabulary};
use yii\debug\models\search\DbSearch;
use yii\debug\Panel;
use yii\debug\panels\db\{DbSnapshot, QueryRow};
use yii\log\Logger;

use function array_filter;
use function array_values;
use function count;
use function hash;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function json_encode;
use function mb_strtoupper;
use function preg_match;

/**
 * Captures every database query emitted during the request and renders them in the Database panel.
 *
 * Hooks the panel-bound DB connection so each prepared statement records its row count, calculates per-query timings
 * from the profile log, and exposes the EXPLAIN action that powers the queries grid's inline plan toggle.
 */
class DbPanel extends Panel
{
    protected const string ICON = 'db';
    protected const string NAME = 'Database';

    /**
     * Critical-query-count threshold; when the captured query count exceeds this value the toolbar item flips to a
     * warning state. `null` disables the check.
     */
    public int|null $criticalQueryThreshold = null;
    /**
     * Application component id of the DB connection used to run EXPLAIN queries.
     */
    public string $db = 'db';
    /**
     * @var array<int, string> Profile log categories scanned for query timings.
     */
    public array $dbEventNames = [
        'yii\db\Command::query',
        'yii\db\Command::execute',
    ];
    /**
     * @var array<string, mixed> Default filter applied to the queries grid as `property => value` (for example,
     * `['type' => 'SELECT']`).
     */
    public array $defaultFilter = [];
    /**
     * @var array<string, int> Default sort order applied to the queries grid as `property => SORT_*` (for example,
     * `['duration' => SORT_DESC]`).
     */
    public array $defaultOrder = [
        'seq' => SORT_ASC,
    ];
    /**
     * Number of DB calls the same backtrace can make before being flagged as an "Excessive Caller". `null` disables
     * the check.
     */
    public int|null $excessiveCallerThreshold = null;
    /**
     * @var array<int, string> Paths whose backtrace frames are skipped when determining the "Caller".
     *
     * Yii framework files are ignored by default. Path aliases are resolved through {@see Yii::getAlias()}.
     */
    public array $ignoredPathsInBacktrace = [];
    /**
     * @var array<int, array<int|string, mixed>>|null Current database profile logs
     */
    private array|null $profileLogs = null;
    private DbSnapshot|null $snapshot = null;
    /**
     * @var array<int, array{
     *   info: string,
     *   category: string,
     *   timestamp: float,
     *   trace: array<int, array<string, mixed>>,
     *   level: int,
     *   duration: float,
     *   memory: int,
     *   memoryDiff: int,
     *   traceHash: string
     * }>|null Current database request timings
     */
    private array|null $timings = null;
    private static string|null $traceHashAlgo = null;

    /**
     * Calculates and caches the per-query timings for the request, dropping backtrace frames that match
     * {@see $ignoredPathsInBacktrace} and tagging each timing with a stable hash of its remaining trace.
     *
     * @return array<int, array{
     *   info: string,
     *   category: string,
     *   timestamp: float,
     *   trace: array<int, array<string, mixed>>,
     *   level: int,
     *   duration: float,
     *   memory: int,
     *   memoryDiff: int,
     *   traceHash: string
     * }> Timings indexed sequentially, each carrying the SQL token, category, capture timestamp, trace, nesting
     * level, duration, memory snapshots, and trace hash.
     */
    public function calculateTimings(): array
    {
        if ($this->timings === null) {
            $this->timings = [];

            $rawTimings = Yii::getLogger()->calculateTimings($this->getProfileLogs());

            $ignoredPathsInBacktrace = array_map(
                Yii::getAlias(...),
                $this->ignoredPathsInBacktrace,
            );

            $hashAlgo = self::traceHashAlgo();

            foreach ($rawTimings as $rawTiming) {
                $timing = self::normalizeTiming($rawTiming);

                if ($timing === null) {
                    continue;
                }

                if ($ignoredPathsInBacktrace !== []) {
                    foreach ($timing['trace'] as $index => $trace) {
                        $file = $trace['file'] ?? null;

                        if (!is_string($file)) {
                            continue;
                        }

                        foreach ($ignoredPathsInBacktrace as $ignoredPathInBacktrace) {
                            if (str_starts_with($file, $ignoredPathInBacktrace)) {
                                unset($timing['trace'][$index]);

                                continue 2;
                            }
                        }
                    }
                }

                $encodedTrace = json_encode($timing['trace']);
                $timing['traceHash'] = hash($hashAlgo, Coerce::string($encodedTrace));

                $this->timings[] = $timing;
            }
        }

        return $this->timings;
    }

    /**
     * Returns whether the given query type produces a useful EXPLAIN plan.
     *
     * Only DML statements that touch tables (`SELECT`, `INSERT`, `UPDATE`, `DELETE`, `REPLACE`, `WITH`) are accepted;
     * metadata, session-control, and transaction-control statements either error or return noise (for example,
     * SQLite PRAGMAs compile down to a handful of VDBE opcodes), so they are filtered out.
     *
     * @param string $type SQL command verb (case-insensitive).
     */
    public static function canBeExplained(string $type): bool
    {
        return in_array(
            mb_strtoupper($type, 'utf8'),
            ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'WITH'],
            true,
        );
    }

    /**
     * Resolves the logger timings into typed query rows and snapshots them.
     */
    public function capture(): DbSnapshot
    {
        return new DbSnapshot($this->resolveRows());
    }

    /**
     * Counts how many times the same backtrace originated a DB query.
     *
     * @return array<string, int> Call counts indexed by the backtrace hash of the caller.
     */
    public function countCallerCals(): array
    {
        $counts = [];

        foreach ($this->resolveRows() as $row) {
            $counts[$row->traceHash] = ($counts[$row->traceHash] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Counts how many times each distinct SQL statement appears in the given timings.
     *
     * @param array<int, array{
     *   info: string,
     *   category: string,
     *   timestamp: float,
     *   trace: array<int, array<string, mixed>>,
     *   level: int,
     *   duration: float,
     *   memory: int,
     *   memoryDiff: int,
     *   traceHash: string
     * }> $timings Timings produced by {@see calculateTimings()}.
     *
     * @return array<string, int> Occurrence counts indexed by SQL statement.
     */
    public function countDuplicateQuery(array $timings): array
    {
        $counts = [];

        foreach ($timings as $timing) {
            $query = $timing['info'];
            $counts[$query] = ($counts[$query] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Returns the DB connection used by the panel for EXPLAIN queries.
     *
     * @throws InvalidConfigException When the configured component id does not resolve to a {@see Connection}.
     */
    public function getDb(): Connection
    {
        $db = Yii::$app->get($this->db);

        if (!$db instanceof Connection) {
            throw new InvalidConfigException(
                "Application component '{$this->db}' must be a DB connection.",
            );
        }

        return $db;
    }

    /**
     * Renders the detail view with the queries grid, the EXPLAIN toggle, and the duplicate-query summary.
     *
     * @throws InvalidConfigException When the DB connection cannot be resolved.
     */
    #[Override]
    public function getDetail(): string
    {
        $searchModel = new DbSearch();

        if (!$searchModel->load(Yii::$app->request->getQueryParams())) {
            $searchModel->load($this->defaultFilter, '');
        }

        $models = $this->getModels();

        $queryDataProvider = $searchModel->search($models);
        $sort = $queryDataProvider->getSort();

        if ($sort instanceof Sort) {
            $sort->defaultOrder = $this->defaultOrder;
        }

        $sumDuplicates = $this->sumDuplicateQueries($models);

        return Yii::$app->view->render(
            'panels/db/detail',
            [
                'hasExplain' => $this->hasExplain(),
                'panel' => $this,
                'queryDataProvider' => $queryDataProvider,
                'searchModel' => $searchModel,
                'sumDuplicates' => $sumDuplicates,
            ],
            $this,
        );
    }

    /**
     * Returns the call counts for backtraces that exceed {@see $excessiveCallerThreshold}.
     *
     * @return array<string, int> Call counts indexed by the backtrace hash of each excessive caller; empty when the
     * check is disabled.
     */
    public function getExcessiveCallers(): array
    {
        if ($this->excessiveCallerThreshold === null) {
            return [];
        }

        return array_filter(
            $this->countCallerCals(),
            fn(int $count): bool => $count >= $this->excessiveCallerThreshold,
        );
    }

    /**
     * Returns the number of distinct backtraces flagged as excessive callers.
     */
    public function getExcessiveCallersCount(): int
    {
        return count($this->getExcessiveCallers());
    }

    /**
     * Returns the profile log entries scanned for query timings (categories listed in {@see $dbEventNames}).
     *
     * @return array<int, array<int|string, mixed>> Profile log entries in capture order.
     */
    public function getProfileLogs(): array
    {
        if ($this->profileLogs === null) {
            $this->profileLogs = $this->getLogMessages(Logger::LEVEL_PROFILE, $this->dbEventNames);
        }

        return $this->profileLogs;
    }

    /**
     * Returns the executed statements as typed rows: the hydrated snapshot when present, otherwise resolved live.
     *
     * @return list<QueryRow> Rows in capture order.
     */
    public function getRows(): array
    {
        return $this->resolveRows();
    }

    /**
     * Returns the distinct SQL statement types captured for the request, keyed and valued by the same uppercase token.
     *
     * @return array<string, string> `type => type` map suitable for a dropdown filter.
     */
    public function getTypes(): array
    {
        $types = [];

        foreach ($this->getModels() as $model) {
            $types[$model->type] = $model->type;
        }

        return $types;
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[Override]
    public function hydrate(array $payload): void
    {
        $this->snapshot = DbSnapshot::fromArray($payload, "$.panels.{$this->id}");

        $this->timings = null;
    }

    /**
     * Registers the `db-explain` action and installs the {@see DebugPdoStatement} class on the panel-bound DB
     * connection so every prepared statement records its `rowCount()`.
     *
     * The hook is applied through {@see \PDO::ATTR_STATEMENT_CLASS} rather than `Connection::$commandClass`, since
     * the latter is not exposed by every Yii 2 fork.
     */
    public function init(): void
    {
        $this->actions['db-explain'] = [
            'class' => ExplainAction::class,
            'panel' => $this,
        ];

        $db = Yii::$app->get($this->db, false);

        if (!$db instanceof Connection) {
            return;
        }

        $apply = static function (Connection $conn): void {
            $conn->pdo?->setAttribute(PDO::ATTR_STATEMENT_CLASS, [DebugPdoStatement::class, []]);
        };

        if ($db->pdo !== null) {
            $apply($db);
        }

        $db->on(
            Connection::EVENT_AFTER_OPEN,
            static function (Event $event) use ($apply): void {
                if ($event->sender instanceof Connection) {
                    $apply($event->sender);
                }
            },
        );
    }

    /**
     * Returns whether the panel can run: requires both a resolvable DB connection and the parent enable check.
     */
    #[Override]
    public function isEnabled(): bool
    {
        try {
            $this->getDb();
        } catch (InvalidConfigException) {
            return false;
        }

        return parent::isEnabled();
    }

    /**
     * Returns whether the given query count exceeds {@see $criticalQueryThreshold}.
     *
     * @param int $count Query count to test.
     */
    public function isQueryCountCritical(int $count): bool
    {
        return ($this->criticalQueryThreshold !== null) && ($count > $this->criticalQueryThreshold);
    }

    /**
     * Returns the number of query rows whose `duplicate` count is greater than one.
     *
     * @param list<QueryRow> $modelData Query rows produced by {@see getModels()}.
     */
    public function sumDuplicateQueries(array $modelData): int
    {
        $numDuplicates = 0;

        foreach ($modelData as $data) {
            if ($data->duplicate > 1) {
                $numDuplicates++;
            }
        }

        return $numDuplicates;
    }

    /**
     * Returns the vocabulary verb suffix for the given SQL command verb.
     *
     * Delegates to {@see Vocabulary::sqlVerb()}: read statements share `get`, `INSERT` shares `post`, in-place
     * mutations share `put`, destructive statements share `delete`, and everything else falls back to `other`.
     */
    public static function typeBadgeVariant(string $type): string
    {
        return Vocabulary::sqlVerb($type);
    }

    /**
     * Returns the typed query rows consumed by the queries grid.
     *
     * @return list<QueryRow> Rows in capture order, suitable for {@see \yii\data\ArrayDataProvider}.
     */
    protected function getModels(): array
    {
        return $this->getRows();
    }

    /**
     * Returns the uppercase SQL command verb extracted from the leading word of the profile-log token.
     *
     * @param string $timing Profile-log token (the captured SQL statement).
     *
     * @return string Uppercase command verb (`SELECT`, `INSERT`, `DELETE`, ...), or `''` when none could be extracted.
     */
    protected function getQueryType(string $timing): string
    {
        $timing = ltrim($timing);

        preg_match('/^([a-zA-z]*)/', $timing, $matches);

        return isset($matches[0]) ? mb_strtoupper($matches[0], 'utf8') : '';
    }

    /**
     * Builds the toolbar items: the query-count chip (flipped to a warning when the count is critical or callers are
     * excessive) and the total-query-time chip.
     *
     * @return array<int, array<string, mixed>> Toolbar items, or `[]` when no queries were captured.
     */
    #[Override]
    protected function getToolbarItems(): array
    {
        $rows = $this->resolveRows();

        $queryCount = count($rows);

        if ($queryCount === 0) {
            return [];
        }

        $excessiveCallerCount = $this->getExcessiveCallersCount();

        $warning = '';

        if ($this->isQueryCountCritical($queryCount)) {
            $warning = "Too many queries, allowed count is {$this->criticalQueryThreshold}.";
        }

        if ($excessiveCallerCount > 0) {
            $separator = $warning !== '' ? "\n" : '';
            $callerLabel = $excessiveCallerCount === 1 ? 'caller is' : 'callers are';
            $warning = "{$warning}{$separator}{$excessiveCallerCount} {$callerLabel} making too many calls.";
        }

        $totalQueryTime = number_format($this->getTotalQueryTime($rows));

        return [
            [
                'status' => $warning !== '' ? 'warning' : 'info',
                'title' => $warning !== '' ? $warning : "Executed $queryCount database queries.",
                'value' => $queryCount,
            ],
            [
                'title' => 'Total query time',
                'value' => "{$totalQueryTime} ms",
            ],
        ];
    }

    /**
     * Returns the sum of every captured query's duration.
     *
     * @param list<QueryRow> $rows Captured query rows.
     *
     * @return float Total query time, in milliseconds.
     */
    protected function getTotalQueryTime(array $rows): float
    {
        $queryTime = 0.0;

        foreach ($rows as $row) {
            $queryTime += $row->duration;
        }

        return $queryTime;
    }

    /**
     * Returns whether the DB connection's driver supports the EXPLAIN action (currently `mysql`, `sqlite`, `pgsql`).
     *
     * @throws InvalidConfigException When the DB connection cannot be resolved.
     */
    protected function hasExplain(): bool
    {
        try {
            $db = $this->getDb();
        } catch (InvalidConfigException) {
            return false;
        }

        return match ($db->getDriverName()) {
            'mysql', 'sqlite', 'pgsql' => true,
            default => false,
        };
    }

    /**
     * Narrows a raw timing returned by the Yii logger into the typed shape consumed by {@see calculateTimings()},
     * or `null` when any required field is missing.
     *
     * @param mixed $rawTiming Raw timing returned by Yii logger.
     *
     * @return array{
     *   info: string,
     *   category: string,
     *   timestamp: float,
     *   trace: array<int, array<string, mixed>>,
     *   level: int,
     *   duration: float,
     *   memory: int,
     *   memoryDiff: int,
     *   traceHash: string
     * }|null Normalized timing, or `null` when the raw payload was incomplete.
     */
    private static function normalizeTiming(mixed $rawTiming): array|null
    {
        if (!is_array($rawTiming)) {
            return null;
        }

        $info = Coerce::stringOrNull($rawTiming['info'] ?? null);
        $timestamp = Coerce::floatOrNull($rawTiming['timestamp'] ?? null);
        $duration = Coerce::floatOrNull($rawTiming['duration'] ?? null);

        if ($info === null || $timestamp === null || $duration === null) {
            return null;
        }

        return [
            'info' => $info,
            'category' => Coerce::stringOrNull($rawTiming['category'] ?? null) ?? '',
            'timestamp' => $timestamp,
            'trace' => Coerce::traceFrames($rawTiming['trace'] ?? []),
            'level' => Coerce::intOrNull($rawTiming['level'] ?? null) ?? 0,
            'duration' => $duration,
            'memory' => Coerce::intOrNull($rawTiming['memory'] ?? null) ?? 0,
            'memoryDiff' => Coerce::intOrNull($rawTiming['memoryDiff'] ?? null) ?? 0,
            'traceHash' => Coerce::stringOrNull($rawTiming['traceHash'] ?? null) ?? '',
        ];
    }

    /**
     * Returns the typed query rows: the hydrated snapshot when present, otherwise resolved from the live logger.
     *
     * @return list<QueryRow> Rows in capture order.
     */
    private function resolveRows(): array
    {
        $snapshot = $this->snapshot;

        if ($snapshot !== null) {
            return $snapshot->entries();
        }

        $timings = $this->calculateTimings();
        $duplicates = $this->countDuplicateQuery($timings);
        $rowCounts = array_values(DebugPdoStatement::$rowCounts);
        $rows = [];

        foreach ($timings as $seq => $timing) {
            $count = $rowCounts[$seq] ?? null;

            $rows[] = QueryRow::fromTiming(
                $timing,
                $this->getQueryType($timing['info']),
                $seq,
                $duplicates[$timing['info']] ?? 1,
                is_int($count) && $count >= 0 ? $count : null,
            );
        }

        return $rows;
    }

    /**
     * Returns the hash algorithm used to fingerprint backtraces.
     *
     * Prefers `xxh3`, falling back to `crc32` on hosts whose PHP installation does not expose it. The answer is
     * cached because {@see hash_algos()} is process-stable.
     */
    private static function traceHashAlgo(): string
    {
        if (self::$traceHashAlgo === null) {
            self::$traceHashAlgo = in_array('xxh3', hash_algos(), true) ? 'xxh3' : 'crc32';
        }

        return self::$traceHashAlgo;
    }
}
