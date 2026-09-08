<?php

declare(strict_types=1);

namespace yii\debug\collectors;

use Closure;
use LogicException;
use PDO;
use PHPForge\Debug\Panel\Db\{DbSnapshot, QueryRow};
use PHPForge\Debug\Panel\Profile\ProfileTimings;
use Yii;
use yii\base\Event;
use yii\db\Connection;
use yii\debug\db\DebugPdoStatement;
use yii\debug\exception\Message;
use yii\debug\LogTarget;
use yii\log\Logger;

use function array_filter;
use function array_pad;
use function array_shift;
use function array_values;
use function count;
use function hash;
use function hash_algos;
use function in_array;
use function is_int;
use function is_string;
use function json_encode;
use function preg_match;
use function strtoupper;

use const JSON_THROW_ON_ERROR;

/**
 * Captures every database query emitted during the request for the Database panel.
 *
 * Hooks the bound DB connection so each prepared statement records its row count, calculates per-query timings from
 * the profile log, and exposes the totals the exported summary adopts (query count, excessive callers).
 *
 * @phpstan-import-type LogTuple from \PHPForge\Debug\Panel\Log\LogSnapshot
 */
class DbCollector extends Collector
{
    /**
     * Application component id of the DB connection whose prepared statements are instrumented.
     */
    public string $db = 'db';
    /**
     * @var list<string> Profile log categories scanned for query timings.
     */
    public array $dbEventNames = [
        'yii\db\Command::query',
        'yii\db\Command::execute',
    ];
    /**
     * Number of DB calls the same backtrace can make before being flagged as an "Excessive Caller". `null` disables
     * the check.
     */
    public int|null $excessiveCallerThreshold = null;
    /**
     * @var list<string> Paths whose backtrace frames are skipped when determining the "Caller".
     *
     * Yii framework files are ignored by default. Path aliases are resolved through {@see Yii::getAlias()}.
     */
    public array $ignoredPathsInBacktrace = [];

    /**
     * @var (Closure(Event): void)|null Active after-open listener, kept so {@see stop()} can detach it.
     */
    private Closure|null $afterOpenListener = null;
    /**
     * Request tag the counts in {@see DebugPdoStatement::$rowCounts} belong to: `false` while no window has been
     * opened, `null` for the window {@see instrument()} opens during the module bootstrap, before the log target
     * exists.
     */
    private string|false|null $countsTag = false;
    /**
     * Whether the statement hook is installed; guards {@see instrument()} against reinstalling the listener and
     * against reopening the row-count window.
     */
    private bool $instrumented = false;
    /**
     * @var list<LogTuple>|null Current database profile logs
     */
    private array|null $profileLogs = null;
    /**
     * @var Connection|null The currently subscribed DB connection.
     */
    private Connection|null $subscribedConnection = null;
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

    /**
     * @var string|null Algorithm used to hash the backtrace for stable identification.
     */
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

            $rawTimings = ProfileTimings::calculate($this->getProfileLogs());

            $ignoredPathsInBacktrace = array_map(Yii::getAlias(...), $this->ignoredPathsInBacktrace);

            $hashAlgo = self::traceHashAlgo();

            foreach ($rawTimings as $timing) {
                if ($ignoredPathsInBacktrace !== []) {
                    foreach ($timing['trace'] as $index => $trace) {
                        $file = $trace['file'] ?? null;

                        if (!is_string($file)) {
                            continue;
                        }

                        foreach ($ignoredPathsInBacktrace as $ignoredPathInBacktrace) {
                            if (str_starts_with($file, $ignoredPathInBacktrace)) {
                                unset($timing['trace'][$index]);
                            }
                        }
                    }

                    $timing['trace'] = array_values($timing['trace']);
                }

                $timing['traceHash'] = hash($hashAlgo, json_encode($timing['trace'], JSON_THROW_ON_ERROR));

                $this->timings[] = $timing;
            }
        }

        return $this->timings;
    }

    /**
     * Resolves the logger timings into typed query rows and snapshots them.
     *
     * @return DbSnapshot|null Captured query payload; `null` when the collector never started.
     */
    public function capture(): DbSnapshot|null
    {
        if (!$this->isStarted()) {
            return null;
        }

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
     * @return list<LogTuple> Profile log entries in capture order.
     */
    public function getProfileLogs(): array
    {
        if ($this->profileLogs === null) {
            $this->profileLogs = $this->getLogMessages(Logger::LEVEL_PROFILE, $this->dbEventNames);
        }

        return $this->profileLogs;
    }

    /**
     * Returns the stable ID pairing this collector with the Database panel.
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'db';
    }

    /**
     * Installs the {@see DebugPdoStatement} class on the bound DB connection so every prepared statement records its
     * `rowCount()`.
     *
     * The hook is applied through {@see PDO::ATTR_STATEMENT_CLASS} rather than `Connection::$commandClass`, since the
     * latter is not exposed by every Yii 2 fork. An {@see Connection::EVENT_AFTER_OPEN} listener covers connections
     * opened later in the request.
     *
     * {@see \yii\debug\Module::initCollectors()} calls this while the debugger bootstraps, ahead of the panels, so
     * queries issued by a panel constructor are counted as well; that first call also opens the row-count window.
     * Reinstallation is skipped while the hook is active, so the counts recorded so far survive {@see start()}.
     */
    public function instrument(): void
    {
        if ($this->instrumented) {
            return;
        }

        $db = Yii::$app->get($this->db, false);

        if (!$db instanceof Connection) {
            return;
        }

        $this->instrumented = true;

        if ($this->countsTag === false) {
            // The log target, and with it the request tag, is wired after the collectors: leave the window anonymous
            // until start() adopts the tag of the request it belongs to.
            $this->countsTag = null;

            DebugPdoStatement::$rowCounts = [];
        }

        $apply = static function (Connection $conn): void {
            $conn->pdo?->setAttribute(PDO::ATTR_STATEMENT_CLASS, [DebugPdoStatement::class, []]);
        };

        if ($db->pdo !== null) {
            $apply($db);
        }

        $this->afterOpenListener = static function (Event $event) use ($apply): void {
            if ($event->sender instanceof Connection) {
                $apply($event->sender);
            }
        };

        $db->on(Connection::EVENT_AFTER_OPEN, $this->afterOpenListener);

        $this->subscribedConnection = $db;
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

        preg_match('/^[a-zA-Z]+/', $timing, $matches);

        return strtoupper($matches[0] ?? '');
    }

    /**
     * Resets the per-request caches, binds the row-count window to the current request tag, and installs the statement
     * hook through {@see instrument()} when the module has not already done so.
     *
     * The counts are discarded only when they belong to another request, so the collector survives being restarted
     * inside one request: a handled exception makes the logger flush twice, and each flush drives
     * {@see LogTarget::export()} through a full shutdown/startup cycle.
     */
    protected function start(): void
    {
        $this->profileLogs = null;
        $this->timings = null;

        $logTarget = $this->module?->logTarget;
        $tag = $logTarget instanceof LogTarget ? $logTarget->tag : null;

        if ($this->countsTag !== null && $this->countsTag !== $tag) {
            DebugPdoStatement::$rowCounts = [];
        }

        $this->countsTag = $tag;

        $this->instrument();
    }

    /**
     * Detaches the after-open listener and clears the per-request caches, so a reused worker process starts clean.
     *
     * The recorded row counts stay in place: they are discarded by the next {@see start()} that belongs to a different
     * request, which keeps them readable when the same request captures more than once.
     */
    protected function stop(): void
    {
        if ($this->afterOpenListener !== null) {
            $this->subscribedConnection?->off(Connection::EVENT_AFTER_OPEN, $this->afterOpenListener);

            $this->afterOpenListener = null;
            $this->subscribedConnection = null;
        }

        $this->instrumented = false;
        $this->profileLogs = null;
        $this->timings = null;
    }

    /**
     * Resolves the typed query rows from the live logger timings.
     *
     * Row counts are appended from the moment {@see instrument()} installs the statement hook and every instrumented
     * execution contributes exactly one entry, so the recorded list maps onto the trailing timings: queries the logger
     * profiled before the hook was in place (the debugger's own bootstrap, or another bootstrap component) report
     * `null`. The list is therefore left-padded to the timing count instead of being read by absolute position, which
     * would shift every count onto the wrong query. Counts outnumbering timings means the profiler missed executions
     * the hook saw, leaving nothing to align against; the whole request falls back to `null`.
     *
     * @return list<QueryRow> Rows in capture order.
     */
    private function resolveRows(): array
    {
        $timings = $this->calculateTimings();
        $duplicates = $this->countDuplicateQuery($timings);

        $rowCounts = DebugPdoStatement::$rowCounts;

        $rows = [];

        $aligned = count($rowCounts) > count($timings) ? [] : array_pad($rowCounts, -count($timings), null);

        foreach ($timings as $seq => $timing) {
            $count = array_shift($aligned);

            $info = $timing['info'];

            if (!isset($duplicates[$info])) {
                throw new LogicException(
                    Message::DUPLICATE_QUERY_COUNT_MISSING->getMessage($info),
                );
            }

            $rows[] = QueryRow::fromTiming(
                $timing,
                $this->getQueryType($info),
                $seq,
                $duplicates[$info],
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
