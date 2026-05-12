<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Yii;
use yii\base\{Event, InvalidConfigException};
use yii\data\Sort;
use yii\db\Connection;
use yii\debug\db\DebugPdoStatement;
use yii\debug\helpers\Coerce;
use yii\debug\models\search\Db;
use yii\debug\Panel;
use yii\log\Logger;

use function count;
use function in_array;
use function is_array;
use function is_int;
use function is_string;

/**
 * Debugger panel that collects and displays database queries performed.
 */
class DbPanel extends Panel
{
    /**
     * Threshold for determining whether the request has involved critical number of DB queries. If the number of
     * queries exceeds this number, the execution is considered taking critical number of DB queries.
     *
     * If it is `null`, this feature is disabled.
     */
    public int|null $criticalQueryThreshold = null;
    /**
     * Name of the database component to use for executing (explain) queries
     */
    public string $db = 'db';
    /**
     * @var array<int, string> Event names used to get profile logs.
     */
    public array $dbEventNames = [
        'yii\db\Command::query',
        'yii\db\Command::execute',
    ];
    /**
     * @var array<string, mixed> Default filter to apply to the database queries. In the format of [ property => value ],
     * for example: [ 'type' => 'SELECT' ]
     */
    public array $defaultFilter = [];
    /**
     * @var array<string, int> Default ordering of the database queries. In the format of [ property => sort direction ],
     * for example: [ 'duration' => SORT_DESC ]
     */
    public array $defaultOrder = [
        'seq' => SORT_ASC,
    ];
    /**
     * Number of DB calls the same backtrace can make before considered an "Excessive Caller". If it is `null`, this
     * feature is disabled.
     */
    public int|null $excessiveCallerThreshold = null;
    /**
     * @var array<int, string> Files and/or paths defined here will be ignored in the determination of DB "Callers".
     * The "Caller" is the backtrace lines that aren't included in the `$ignoredPathsInBacktrace`, Yii files are ignored
     * by default.
     * Hint: You can use path aliases here.
     */
    public array $ignoredPathsInBacktrace = [];
    /**
     * @var array<int, array{
     *   type: string,
     *   query: string,
     *   duration: float,
     *   trace: array<int, array<string, mixed>>,
     *   traceHash: string,
     *   timestamp: float,
     *   seq: int,
     *   duplicate: int,
     *   rows: int|null
     * }>|null DB queries info extracted to array as models, to use with data provider.
     */
    private array|null $models = null;
    /**
     * @var array<int, array<int|string, mixed>>|null Current database profile logs
     */
    private array|null $profileLogs = null;
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
     * Calculates given request profile timings.
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
     * }> timings [token, category, timestamp, traces, nesting level, elapsed time]
     */
    public function calculateTimings(): array
    {
        if ($this->timings === null) {
            $this->timings = [];

            $rawTimings = Yii::getLogger()->calculateTimings($this->getMessagesForTimings());

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
                $timing['traceHash'] = hash($hashAlgo, is_string($encodedTrace) ? $encodedTrace : '');

                $this->timings[] = $timing;
            }
        }

        return $this->timings;
    }

    /**
     * Check if given query type can be explained.
     *
     * @param string $type query type
     */
    public static function canBeExplained(string $type): bool
    {
        /**
         * Only DML statements that touch tables produce a meaningful plan. Skip metadata, session-control and
         * transaction-control statements where EXPLAIN either errors or returns noise (for example. SQLite PRAGMAs that
         * compile down to a few VDBE opcodes).
         */
        return in_array(
            mb_strtoupper($type, 'utf8'),
            ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'WITH'],
            true,
        );
    }

    /**
     * Counts the number of times the same backtrace makes a DB query.
     *
     * @return array<string, int> Number of DB calls indexed by the backtrace hash of the caller.
     */
    public function countCallerCals(): array
    {
        $counts = [];

        foreach ($this->calculateTimings() as $timing) {
            $traceHash = $timing['traceHash'];
            $counts[$traceHash] = ($counts[$traceHash] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Return associative array, where key is query string
     * and value is number of occurrences the same query in array.
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
     * }> $timings
     *
     * @return array<string, int>
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
     * Returns a reference to the DB component associated with the panel
     *
     * @throws InvalidConfigException
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
     * @throws InvalidConfigException
     */
    public function getDetail(): string
    {
        $searchModel = new Db();

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
        );
    }

    /**
     * Get the backtrace hashes that make excessive DB cals.
     *
     * @return array<string, int> Number of DB calls indexed by the backtrace hash of excessive caller(s).
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
     * Get the number of excessive caller(s).
     */
    public function getExcessiveCallersCount(): int
    {
        return count($this->getExcessiveCallers());
    }

    public function getName(): string
    {
        return 'Database';
    }

    /**
     * Returns all profile logs of the current request for this panel. It includes categories specified in
     * $this->dbEventNames property.
     *
     * @return array<int, array<int|string, mixed>>
     */
    public function getProfileLogs(): array
    {
        if ($this->profileLogs === null) {
            $this->profileLogs = $this->getLogMessages(Logger::LEVEL_PROFILE, $this->dbEventNames);
        }

        return $this->profileLogs;
    }

    public function getSummary(): string
    {
        $timings = $this->calculateTimings();

        $queryCount = count($timings);
        $queryTime = number_format($this->getTotalQueryTime($timings) * 1000) . ' ms';

        $excessiveCallerCount = $this->getExcessiveCallersCount();

        return Yii::$app->view->render(
            'panels/db/summary',
            [
                'excessiveCallerCount' => $excessiveCallerCount,
                'panel' => $this,
                'queryCount' => $queryCount,
                'queryTime' => $queryTime,
                'timings' => $timings,
            ],
        );
    }

    /**
     * @return string short name of the panel, which will be use in summary.
     */
    public function getSummaryName(): string
    {
        return 'DB';
    }

    public function getToolbarIcon(): string
    {
        return 'db';
    }

    /**
     * Returns array query types
     *
     * @return array<string, string>
     */
    public function getTypes(): array
    {
        $types = [];

        foreach ($this->getModels() as $model) {
            $types[$model['type']] = $model['type'];
        }

        return $types;
    }

    public function init(): void
    {
        $this->actions['db-explain'] = [
            'class' => 'yii\\debug\\actions\\db\\ExplainAction',
            'panel' => $this,
        ];

        /**
         * Hook the panel-bound DB component so every prepared statement records its rowCount.
         *
         * We swap PDOStatement subclass via the PDO attribute works across Yii 2 forks since it does not depend on
         * {@see Connection::$commandClass} (which not every fork exposes).
         */
        $db = Yii::$app->get($this->db, false);

        if (!$db instanceof Connection) {
            return;
        }

        $apply = static function (Connection $conn): void {
            $conn->pdo?->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [DebugPdoStatement::class, []]);
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

    public function isEnabled(): bool
    {
        try {
            $this->getDb();
        } catch (InvalidConfigException $exception) {
            return false;
        }

        return parent::isEnabled();
    }

    /**
     * Check if the number of calls by "Caller" is excessive according to the settings.
     *
     * @param int $numCalls queries count
     */
    public function isNumberOfCallsExcessive(int $numCalls): bool
    {
        return ($this->excessiveCallerThreshold !== null) && ($numCalls > $this->excessiveCallerThreshold);
    }

    /**
     * Check if given queries count is critical according to the settings.
     *
     * @param int $count queries count
     */
    public function isQueryCountCritical(int $count): bool
    {
        return ($this->criticalQueryThreshold !== null) && ($count > $this->criticalQueryThreshold);
    }

    /**
     * @return array{messages: array<int, array<int|string, mixed>>, rowCounts: array<int, int>}
     */
    public function save(): array
    {
        return [
            'messages' => $this->getProfileLogs(),
            'rowCounts' => DebugPdoStatement::$rowCounts,
        ];
    }

    /**
     * Returns sum of all duplicated queries
     *
     * @param array<int, array{
     *   type: string,
     *   query: string,
     *   duration: float,
     *   trace: array<int, array<string, mixed>>,
     *   traceHash: string,
     *   timestamp: float,
     *   seq: int,
     *   duplicate: int,
     *   rows: int|null
     * }> $modelData
     */
    public function sumDuplicateQueries(array $modelData): int
    {
        $numDuplicates = 0;

        foreach ($modelData as $data) {
            if ($data['duplicate'] > 1) {
                $numDuplicates++;
            }
        }

        return $numDuplicates;
    }

    /**
     * Returns the badge variant for a query type.
     *
     * Maps SQL command verbs to a visual class so the queries grid can render a colored pill (info / success / warning /
     * danger / muted) at a glance.
     */
    public static function typeBadgeVariant(string $type): string
    {
        return match (strtoupper($type)) {
            'SELECT', 'SHOW', 'EXPLAIN', 'DESCRIBE', 'PRAGMA' => 'info',
            'INSERT' => 'success',
            'UPDATE', 'REPLACE', 'UPSERT' => 'warning',
            'DELETE', 'DROP', 'TRUNCATE' => 'danger',
            default => 'muted',
        };
    }

    /**
     * Returns an array of models that represents logs of the current request.
     *
     * Can be used with data providers such as {@see \yii\data\ArrayDataProvider}.
     *
     * @return array<int, array{
     *   type: string,
     *   query: string,
     *   duration: float,
     *   trace: array<int, array<string, mixed>>,
     *   traceHash: string,
     *   timestamp: float,
     *   seq: int,
     *   duplicate: int,
     *   rows: int|null
     * }>
     */
    protected function getModels(): array
    {
        if ($this->models === null) {
            $this->models = [];

            $timings = $this->calculateTimings();
            $duplicates = $this->countDuplicateQuery($timings);
            $rowCounts = $this->getSavedRowCounts();

            $rowCountIndex = 0;

            foreach ($timings as $seq => $dbTiming) {
                $rows = $rowCounts[$rowCountIndex] ?? null;
                $rowCountIndex++;

                $this->models[] = [
                    'type' => $this->getQueryType($dbTiming['info']),
                    'query' => $dbTiming['info'],
                    'duration' => ($dbTiming['duration'] * 1000), // in milliseconds
                    'trace' => $dbTiming['trace'],
                    'traceHash' => $dbTiming['traceHash'],
                    'timestamp' => ($dbTiming['timestamp'] * 1000), // in milliseconds
                    'seq' => $seq,
                    'duplicate' => $duplicates[$dbTiming['info']] ?? 1,
                    'rows' => is_int($rows) && $rows >= 0 ? $rows : null,
                ];
            }
        }

        return $this->models;
    }

    /**
     * Returns database query type.
     *
     * @param string $timing Timing procedure string.
     *
     * @return string Query type such as select, insert, delete, etc.
     */
    protected function getQueryType(string $timing): string
    {
        $timing = ltrim($timing);

        preg_match('/^([a-zA-z]*)/', $timing, $matches);

        return isset($matches[0]) ? mb_strtoupper($matches[0], 'utf8') : '';
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function getToolbarItems(): array|null
    {
        $timings = $this->calculateTimings();

        $queryCount = count($timings);

        if ($queryCount === 0) {
            return null;
        }

        $excessiveCallerCount = $this->getExcessiveCallersCount();

        $warning = '';

        if ($this->isQueryCountCritical($queryCount)) {
            $warning = "Too many queries, allowed count is {$this->criticalQueryThreshold}.";
        }

        if ($excessiveCallerCount > 0) {
            $warning .= ($warning !== '' ? "\n" : '') . $excessiveCallerCount . ' '
                . ($excessiveCallerCount === 1 ? 'caller is' : 'callers are')
                . ' making too many calls.';
        }

        return [
            [
                'status' => $warning !== '' ? 'warning' : 'info',
                'title' => $warning !== '' ? $warning : "Executed $queryCount database queries.",
                'value' => $queryCount,
            ],
            [
                'title' => 'Total query time',
                'value' => number_format($this->getTotalQueryTime($timings) * 1000) . ' ms',
            ],
        ];
    }

    /**
     * Returns total query time.
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
     * }> $timings
     *
     * @return float total time
     */
    protected function getTotalQueryTime(array $timings): float
    {
        $queryTime = 0.0;

        foreach ($timings as $timing) {
            $queryTime += $timing['duration'];
        }

        return $queryTime;
    }

    /**
     * @throws InvalidConfigException
     *
     * @return bool Whether the DB component has support for EXPLAIN queries.
     */
    protected function hasExplain(): bool
    {
        try {
            $db = $this->getDb();
        } catch (InvalidConfigException $e) {
            return false;
        }

        return match ($db->getDriverName()) {
            'mysql', 'sqlite', 'pgsql' => true,
            default => false,
        };
    }

    /**
     * Returns the saved profile messages for timing calculation.
     *
     * @return array<int, array<int|string, mixed>>
     */
    private function getMessagesForTimings(): array
    {
        $data = is_array($this->data) ? $this->data : [];

        $messages = $data['messages'] ?? null;

        if (!is_array($messages)) {
            return $this->getProfileLogs();
        }

        return self::normalizeMessages($messages);
    }

    /**
     * Returns the saved row counts captured by {@see DebugPdoStatement}.
     *
     * @return array<int, int>
     */
    private function getSavedRowCounts(): array
    {
        $data = is_array($this->data) ? $this->data : [];

        $rowCounts = $data['rowCounts'] ?? DebugPdoStatement::$rowCounts;

        if (!is_array($rowCounts)) {
            return DebugPdoStatement::$rowCounts;
        }

        $normalized = [];

        foreach ($rowCounts as $rowCount) {
            if (is_int($rowCount)) {
                $normalized[] = $rowCount;
            }
        }

        return $normalized;
    }

    /**
     * @param array<int|string, mixed> $messages
     *
     * @return array<int, array<int|string, mixed>>
     */
    private static function normalizeMessages(array $messages): array
    {
        $normalized = [];

        foreach ($messages as $message) {
            if (is_array($message)) {
                $normalized[] = $message;
            }
        }

        return $normalized;
    }

    /**
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
     * }|null
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
     * Returns the hash algorithm used to fingerprint backtraces, falling back to `crc32` on hosts whose PHP
     * installation does not expose xxh3 (`hash_algos()` is process-stable, so we compute the answer once).
     */
    private static function traceHashAlgo(): string
    {
        if (self::$traceHashAlgo === null) {
            self::$traceHashAlgo = in_array('xxh3', hash_algos(), true) ? 'xxh3' : 'crc32';
        }

        return self::$traceHashAlgo;
    }
}
