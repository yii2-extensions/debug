<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Panel\Db\{DbSnapshot, QueryRow};
use Yii;
use yii\base\InvalidConfigException;
use yii\data\Sort;
use yii\db\Connection;
use yii\debug\actions\db\ExplainAction;
use yii\debug\collectors\DbCollector;
use yii\debug\models\search\DbSearch;
use yii\debug\Panel;

use function array_filter;
use function count;

/**
 * Renders the database queries captured by the Database collector.
 *
 * Presents the queries grid with per-query timings, the duplicate-query summary, and the EXPLAIN action that powers
 * the grid's inline plan toggle; data acquisition lives in {@see DbCollector}.
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

    private DbSnapshot|null $snapshot = null;

    /**
     * Counts how many times the same backtrace originated a DB query.
     *
     * @return array<string, int> Call counts indexed by the backtrace hash of the caller.
     */
    public function countCallerCals(): array
    {
        $counts = [];

        foreach ($this->getRows() as $row) {
            $counts[$row->traceHash] = ($counts[$row->traceHash] ?? 0) + 1;
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
     * Returns the call counts for backtraces that exceed the Database collector's excessive-caller threshold.
     *
     * @return array<string, int> Call counts indexed by the backtrace hash of each excessive caller; empty when the
     * check is disabled.
     */
    public function getExcessiveCallers(): array
    {
        $threshold = $this->excessiveCallerThreshold();

        if ($threshold === null) {
            return [];
        }

        return array_filter(
            $this->countCallerCals(),
            static fn(int $count): bool => $count >= $threshold,
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
     * Returns the executed statements as typed rows hydrated from the stored snapshot.
     *
     * @return list<QueryRow> Rows in capture order.
     */
    public function getRows(): array
    {
        return $this->snapshot?->entries() ?? [];
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
    }

    /**
     * Registers the `db-explain` action.
     */
    public function init(): void
    {
        parent::init();

        $this->actions['db-explain'] = ExplainAction::class;
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
     * Returns the typed query rows consumed by the queries grid.
     *
     * @return list<QueryRow> Rows in capture order, suitable for {@see \yii\data\ArrayDataProvider}.
     */
    protected function getModels(): array
    {
        return $this->getRows();
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
        $rows = $this->getRows();

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
     * Returns the Database collector's excessive-caller threshold, or `null` when the check is disabled or the
     * collector is not registered.
     */
    private function excessiveCallerThreshold(): int|null
    {
        $collector = $this->module?->getCollectorCoordinator()->collector('db');

        return $collector instanceof DbCollector ? $collector->excessiveCallerThreshold : null;
    }
}
