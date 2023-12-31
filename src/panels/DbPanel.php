<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\panels;

use Yii;
use yii\base\InvalidConfigException;
use yii\data\ArrayDataProvider;
use yii\db\Connection;
use yii\debug\actions\db\ExplainAction;
use yii\debug\models\search\Db;
use yii\debug\Panel;
use yii\helpers\ArrayHelper;

use function array_count_values;
use function array_filter;
use function array_key_exists;
use function array_reduce;
use function count;
use function ltrim;
use function mb_strtoupper;
use function number_format;
use function preg_match;

/**
 * Debugger panel that collects and displays database queries performed.
 *
 * @property array $excessiveCallers The number of DB calls indexed by the backtrace hash of excessive
 * caller(s).
 * @property array $profileLogs
 * @property string $summaryName Short name of the panel, which will be use in summary.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 *
 * @since 2.0
 */
class DbPanel extends Panel
{
    /**
     * @var int|null the threshold for determining whether the request has involved a critical number of DB queries.
     * If the number of queries exceeds this number, the execution is considered taking critical number of DB queries.
     * If it is `null`, this feature is disabled.
     */
    public int|null $criticalQueryThreshold = null;
    /**
     * @var int|null the number of DB calls the same backtrace can make before considered an "Excessive Caller."
     * If it is `null`, this feature is disabled.
     * Note: Changes will only be reflected in new requests.
     */
    public int|null $excessiveCallerThreshold = null;
    /**
     * @var string[] the files and/or paths defined here will be ignored in the determination of DB "Callers."
     * The "Caller" is the backtrace lines that aren't included in the `$ignoredPathsInBacktrace`
     * Yii files are ignored by default.
     * Hint: You can use path aliases here.
     */
    public array $ignoredPathsInBacktrace = [];
    /**
     * @var string the name of the database component to use for executing (explain) queries
     */
    public string $db = 'db';
    /**
     * @var array the default ordering of the database queries. In the format of
     * [ property => sort direction ], for example: [ 'duration' => SORT_DESC ]
     */
    public array $defaultOrder = [
        'seq' => SORT_ASC,
    ];
    /**
     * @var array the default filter to apply to the database queries. In the format
     * of [ property => value ], for example: [ 'type' => 'SELECT' ]
     */
    public array $defaultFilter = [];

    /**
     * @var array of event names used to get profile logs.
     */
    public array $dbEventNames = ['yii\db\Command::query', 'yii\db\Command::execute'];
    /**
     * @var array db queries info extracted to array as models, to use with data provider.
     */
    private array $_models = [];
    /**
     * @var array current database request timings
     */
    private array $_timings = [];
    /**
     * @var array current database profile logs
     */
    private array $_profileLogs = [];

    public function init(): void
    {
        $this->actions['db-explain'] = [
            'class' => ExplainAction::class,
            'panel' => $this,
        ];
    }

    public function getName(): string
    {
        return 'Database';
    }

    /**
     * @return string short name of the panel, which will be used in summary.
     */
    public function getSummaryName(): string
    {
        return 'DB';
    }

    public function getSummary(): string
    {
        $timings = $this->calculateTimings();
        $queryCount = count($timings);
        $queryTime = number_format($this->getTotalQueryTime($timings) * 1000) . ' ms';
        $excessiveCallerCount = $this->getExcessiveCallersCount();

        return Yii::$app->view->render('panels/db/summary', [
            'timings' => $this->calculateTimings(),
            'panel' => $this,
            'queryCount' => $queryCount,
            'queryTime' => $queryTime,
            'excessiveCallerCount' => $excessiveCallerCount,
        ]);
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

        $models = $this->_models;
        $queryDataProvider = $searchModel->search($models);
        $queryDataProvider->getSort()->defaultOrder = $this->defaultOrder;
        $sumDuplicates = $this->sumDuplicateQueries($models);
        $callerDataProvider = $this->generateQueryCallersDataProvider($models);

        return Yii::$app->view->render('panels/db/detail', [
            'panel' => $this,
            'queryDataProvider' => $queryDataProvider,
            'callerDataProvider' => $callerDataProvider,
            'searchModel' => $searchModel,
            'hasExplain' => $this->hasExplain(),
            'sumDuplicates' => $sumDuplicates,
        ]);
    }

    /**
     * Calculates given request profile timings.
     *
     * @return array timings [token, category, timestamp, traces, nesting level, elapsed time]
     */
    public function calculateTimings(): array
    {
        return $this->_timings;
    }

    public function save(): mixed
    {
        return ['messages' => $this->getProfileLogs()];
    }

    /**
     * Returns all profile logs of the current request for this panel. It includes categories specified in $this->dbEventNames property.
     */
    public function getProfileLogs(): array
    {
        return $this->_profileLogs;
    }

    /**
     * Return associative array, where key is query string and value is number of occurrences the same query in an array.
     */
    public function countDuplicateQuery($timings): array
    {
        $query = ArrayHelper::getColumn($timings, 'info');

        return array_count_values($query);
    }

    /**
     * Returns sum of all duplicated queries.
     */
    public function sumDuplicateQueries($modelData): int
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
     * Counts the number of times the same backtrace makes a DB query.
     *
     * @return array the number of DB calls indexed by the backtrace hash of the caller.
     */
    public function countCallerCals(): array
    {
        $query = ArrayHelper::getColumn($this->calculateTimings(), 'traceHash');

        return array_count_values($query);
    }

    /**
     * Get the backtrace hashes that make excessive DB cals.
     *
     * @return array the number of DB calls indexed by the backtrace hash of excessive caller(s).
     */
    public function getExcessiveCallers(): array
    {
        if ($this->excessiveCallerThreshold === null) {
            return [];
        }

        return array_filter(
            $this->countCallerCals(),
            function ($count) {
                return $count >= $this->excessiveCallerThreshold;
            }
        );
    }

    /**
     * Get the number of excessive caller(s).
     */
    public function getExcessiveCallersCount(): int
    {
        return count($this->getExcessiveCallers());
    }

    /**
     * Creates an ArrayDataProvider for the DB query callers.
     */
    public function generateQueryCallersDataProvider(array $modelData): ArrayDataProvider
    {
        $callers = [];
        foreach ($modelData as $data) {
            if (!array_key_exists($data['traceHash'], $callers)) {
                $callers[$data['traceHash']] = [
                    'trace' => $data['trace'],
                    'numCalls' => 0,
                    'totalDuration' => 0,
                    'queries' => [],
                ];
            }
            ++$callers[$data['traceHash']]['numCalls'];
            $callers[$data['traceHash']]['totalDuration'] += $data['duration'];
            $callers[$data['traceHash']]['queries'][] = [
                'timestamp' => $data['timestamp'],
                'duration' => $data['duration'],
                'query' => $data['query'],
                'type' => $data['type'],
                'seq' => $data['seq'],
            ];
        }

        return new ArrayDataProvider([
            'allModels' => $callers,
            'pagination' => false,
            'sort' => [
                'attributes' => ['numCalls', 'totalDuration'],
                'defaultOrder' => ['numCalls' => SORT_DESC],
            ],
        ]);
    }

    /**
     * Check if given queries count is critical, according to the settings.
     *
     * @param int $count queries count
     */
    public function isQueryCountCritical(int $count): bool
    {
        return ($this->criticalQueryThreshold !== null) && ($count > $this->criticalQueryThreshold);
    }

    /**
     * Check if the number of calls by "Caller" is excessive, according to the settings.
     *
     * @param int $numCalls queries count
     */
    public function isNumberOfCallsExcessive(int $numCalls): bool
    {
        return ($this->excessiveCallerThreshold !== null) && ($numCalls > $this->excessiveCallerThreshold);
    }

    /**
     * Returns array query types.
     */
    public function getTypes(): array
    {
        return array_reduce(
            $this->_models,
            static function ($result, $item) {
                $result[$item['type']] = $item['type'];
                return $result;
            },
            []
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
     * Check if a given query type can be explained.
     *
     * @param string $type query type.
     */
    public static function canBeExplained(string $type): bool
    {
        return $type !== 'SHOW';
    }

    /**
     * Returns a reference to the DB component associated with the panel
     *
     * @throws InvalidConfigException
     */
    public function getDb(): Connection
    {
        return Yii::$app->get($this->db);
    }

    /**
     * Returns total query time.
     *
     * @return int total time
     */
    protected function getTotalQueryTime(array $timings): int
    {
        $queryTime = 0;

        foreach ($timings as $timing) {
            $queryTime += $timing['duration'];
        }

        return $queryTime;
    }

    /**
     * Returns an array of models that represents logs of the current request.
     * Can be used with data providers such as \yii\data\ArrayDataProvider.
     *
     * @return array models
     */
    protected function getModels(): array
    {
        return $this->_models;
    }

    /**
     * Returns database query type.
     *
     * @param string $timing timing procedure string
     *
     * @return string query type such as select, insert, delete, etc.
     */
    protected function getQueryType(string $timing): string
    {
        $timing = ltrim($timing);
        preg_match('/^([A-z]*)/', $timing, $matches);

        return count($matches) ? mb_strtoupper($matches[0], 'utf8') : '';
    }

    /**
     * @throws InvalidConfigException
     *
     * @return bool Whether the DB component has support for EXPLAIN queries
     */
    protected function hasExplain(): bool
    {
        $db = $this->getDb();

        return match ($db->getDriverName()) {
            'mysql', 'sqlite', 'pgsql' => true,
            default => false,
        };
    }
}
