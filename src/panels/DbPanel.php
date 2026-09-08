<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Panel\Db\{DbMessage, DbSnapshot, DbSummary, DbSummaryRenderer, QueryRow};
use PHPForge\Debug\Panel\{PanelIcon, PanelTitle};
use Yii;
use yii\base\InvalidConfigException;
use yii\data\Sort;
use yii\db\Connection;
use yii\debug\actions\db\ExplainAction;
use yii\debug\collectors\DbCollector;
use yii\debug\exception\Message;
use yii\debug\models\search\DbSearch;
use yii\debug\Panel;

/**
 * Renders the database queries captured by the Database collector.
 *
 * Presents the queries grid with per-query timings, the duplicate-query summary, and the EXPLAIN action that powers
 * the grid's inline plan toggle; data acquisition lives in {@see DbCollector}.
 */
class DbPanel extends Panel
{
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

    /**
     * The captured database snapshot for the current request.
     */
    private DbSnapshot|null $snapshot = null;
    /**
     * The computed summary of the captured database snapshot.
     */
    private DbSummary|null $summary = null;

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
                Message::DB_COMPONENT_INVALID->getMessage($this->db),
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

        return Yii::$app->view->render(
            'panels/db/detail',
            [
                'hasExplain' => $this->hasExplain(),
                'panel' => $this,
                'queryDataProvider' => $queryDataProvider,
                'searchModel' => $searchModel,
            ],
            $this,
        );
    }

    /**
     * Returns the panel display name from the shared title enum.
     */
    #[Override]
    public function getName(): string
    {
        return PanelTitle::DATABASE->value;
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
     * Returns the request-wide query metrics, computed once per hydrated snapshot and reused by every consumer.
     */
    public function getSummary(): DbSummary
    {
        return $this->summary ??= new DbSummary($this->getRows());
    }

    /**
     * Returns the icon key from the shared panel icon enum.
     */
    #[Override]
    public function getToolbarIcon(): string
    {
        return PanelIcon::DATABASE->value;
    }

    /**
     * Returns the distinct SQL statement types captured for the request, keyed and valued by the same uppercase token.
     *
     * @return array<string, string> `type => type` map suitable for a dropdown filter.
     */
    public function getTypes(): array
    {
        return $this->getSummary()->types;
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[Override]
    public function hydrate(array $payload): void
    {
        $this->snapshot = DbSnapshot::fromArray($payload, "$.panels.{$this->id}");

        $this->summary = null;
    }

    /**
     * Registers the `db-explain` action.
     */
    public function init(): void
    {
        // Yii lifecycle convention: the parent chain is a no-op today, so removing this call is unobservable.
        // @infection-ignore-all
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
        $summary = $this->getSummary();

        if ($summary->count === 0) {
            return [];
        }

        $excessiveCallerThreshold = $this->excessiveCallerThreshold();

        $totalQueryTime = number_format($this->getTotalQueryTime());

        return [
            [
                'status' => $summary->hasWarning($this->criticalQueryThreshold, $excessiveCallerThreshold)
                    ? 'warning'
                    : 'info',
                'title' => DbSummaryRenderer::toolbarTitle(
                    $summary,
                    $this->criticalQueryThreshold,
                    $excessiveCallerThreshold,
                ),
                'value' => $summary->count,
            ],
            [
                'title' => DbMessage::TOTAL_TIME->value,
                'value' => "{$totalQueryTime} ms",
            ],
        ];
    }

    /**
     * Returns the sum of every captured query's duration.
     *
     * @return float Total query time, in milliseconds.
     */
    protected function getTotalQueryTime(): float
    {
        return $this->getSummary()->duration;
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
