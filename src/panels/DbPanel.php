<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\panels;

use Yii;
use yii\base\InvalidConfigException;
use yii\debug\models\search\Db;
use yii\debug\Panel;
use yii\helpers\ArrayHelper;
use yii\log\Logger;

/**
 * Debugger panel that collects and displays database queries performed.
 *
 * @property \yii\db\Connection $db
 * @property array $excessiveCallers The number of DB calls indexed by the backtrace hash of excessive
 * caller(s).
 * @property int $excessiveCallersCount
 * @property array $profileLogs
 * @property string $summaryName Short name of the panel, which will be use in summary.
 * @property array<string, string> $types
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class DbPanel extends Panel
{
    /**
     * @var int|null the threshold for determining whether the request has involved
     * critical number of DB queries. If the number of queries exceeds this number,
     * the execution is considered taking critical number of DB queries.
     * If it is `null`, this feature is disabled.
     */
    public $criticalQueryThreshold;
    /**
     * @var string the name of the database component to use for executing (explain) queries
     */
    public $db = 'db';


    /**
     * @var array of event names used to get profile logs.
     * @since 2.1.17
     */
    public $dbEventNames = ['yii\db\Command::query', 'yii\db\Command::execute'];
    /**
     * @var array the default filter to apply to the database queries. In the format
     * of [ property => value ], for example: [ 'type' => 'SELECT' ]
     * @since 2.0.7
     */
    public $defaultFilter = [];
    /**
     * @var array the default ordering of the database queries. In the format of
     * [ property => sort direction ], for example: [ 'duration' => SORT_DESC ]
     * @since 2.0.7
     */
    public $defaultOrder = [
        'seq' => SORT_ASC,
    ];
    /**
     * @var int|null the number of DB calls the same backtrace can make before considered an "Excessive Caller".
     * If it is `null`, this feature is disabled.
     * Note: Changes will only be reflected in new requests.
     * @since 2.1.23
     */
    public $excessiveCallerThreshold = null;
    /**
     * @var string[] the files and/or paths defined here will be ignored in the determination of DB "Callers".
     * The "Caller" is the backtrace lines that aren't included in the `$ignoredPathsInBacktrace`,
     * Yii files are ignored by default.
     * Hint: You can use path aliases here.
     * @since 2.1.23
     */
    public $ignoredPathsInBacktrace = [];
    /**
     * @var array db queries info extracted to array as models, to use with data provider.
     */
    private $_models;
    /**
     * @var array current database profile logs
     */
    private $_profileLogs;
    /**
     * @var array current database request timings
     */
    private $_timings;

    /**
     * Calculates given request profile timings.
     *
     * @return array timings [token, category, timestamp, traces, nesting level, elapsed time]
     */
    public function calculateTimings()
    {
        if ($this->_timings === null) {
            $this->_timings = Yii::getLogger()->calculateTimings($this->data['messages'] ?? $this->getProfileLogs());

            // Parse aliases
            $ignoredPathsInBacktrace = array_map(
                function ($path) {
                    return Yii::getAlias($path);
                },
                $this->ignoredPathsInBacktrace,
            );

            // Generate hash for caller
            $hashAlgo = in_array('xxh3', hash_algos(), true) ? 'xxh3' : 'crc32';
            foreach ($this->_timings as &$timing) {
                if ($ignoredPathsInBacktrace) {
                    foreach ($timing['trace'] as $index => $trace) {
                        foreach ($ignoredPathsInBacktrace as $ignoredPathInBacktrace) {
                            if (isset($trace['file']) && strpos($trace['file'], $ignoredPathInBacktrace) === 0) {
                                unset($timing['trace'][$index]);
                                continue 2;
                            }
                        }
                    }
                }
                $timing['traceHash'] = hash($hashAlgo, json_encode($timing['trace']));
            }
        }

        return $this->_timings;
    }

    /**
     * Check if given query type can be explained.
     *
     * @param string $type query type
     * @return bool
     *
     * @since 2.0.5
     */
    public static function canBeExplained($type)
    {
        // Only DML statements that touch tables produce a meaningful plan. Skip metadata,
        // session-control and transaction-control statements where EXPLAIN either errors
        // or returns noise (e.g. SQLite PRAGMAs that compile down to a few VDBE opcodes).
        return in_array(
            mb_strtoupper((string) $type, 'utf8'),
            ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'WITH'],
            true,
        );
    }

    /**
     * Counts the number of times the same backtrace makes a DB query.
     *
     * @return array the number of DB calls indexed by the backtrace hash of the caller.
     * @since 2.1.23
     */
    public function countCallerCals()
    {
        $query = ArrayHelper::getColumn($this->calculateTimings(), 'traceHash');

        return array_count_values($query);
    }

    /**
     * Return associative array, where key is query string
     * and value is number of occurrences the same query in array.
     *
     * @return array
     * @since 2.0.13
     */
    public function countDuplicateQuery($timings)
    {
        $query = ArrayHelper::getColumn($timings, 'info');

        return array_count_values($query);
    }

    /**
     * Returns a reference to the DB component associated with the panel
     *
     * @throws InvalidConfigException
     * @return \yii\db\Connection
     * @since 2.0.5
     */
    public function getDb()
    {
        return Yii::$app->get($this->db);
    }

    /**
     * @throws InvalidConfigException
     */
    public function getDetail()
    {
        $searchModel = new Db();

        if (!$searchModel->load(Yii::$app->request->getQueryParams())) {
            $searchModel->load($this->defaultFilter, '');
        }

        $models = $this->getModels();
        $queryDataProvider = $searchModel->search($models);
        $queryDataProvider->getSort()->defaultOrder = $this->defaultOrder;
        $sumDuplicates = $this->sumDuplicateQueries($models);

        return Yii::$app->view->render('panels/db/detail', [
            'panel' => $this,
            'queryDataProvider' => $queryDataProvider,
            'searchModel' => $searchModel,
            'hasExplain' => $this->hasExplain(),
            'sumDuplicates' => $sumDuplicates,
        ]);
    }

    /**
     * Get the backtrace hashes that make excessive DB cals.
     *
     * @return array the number of DB calls indexed by the backtrace hash of excessive caller(s).
     * @since 2.1.23
     */
    public function getExcessiveCallers()
    {
        if ($this->excessiveCallerThreshold === null) {
            return [];
        }

        return array_filter(
            $this->countCallerCals(),
            function ($count) {
                return $count >= $this->excessiveCallerThreshold;
            },
        );
    }

    /**
     * Get the number of excessive caller(s).
     *
     * @return int
     * @since 2.1.23
     */
    public function getExcessiveCallersCount()
    {
        return count($this->getExcessiveCallers());
    }

    public function getName()
    {
        return 'Database';
    }

    /**
     * Returns all profile logs of the current request for this panel. It includes categories specified in $this->dbEventNames property.
     * @return array
     */
    public function getProfileLogs()
    {
        if ($this->_profileLogs === null) {
            $this->_profileLogs = $this->getLogMessages(Logger::LEVEL_PROFILE, $this->dbEventNames);
        }

        return $this->_profileLogs;
    }

    public function getSummary()
    {
        $timings = $this->calculateTimings();
        $queryCount = count($timings);
        $queryTime = number_format($this->getTotalQueryTime($timings) * 1000) . ' ms';
        $excessiveCallerCount = $this->getExcessiveCallersCount();

        return Yii::$app->view->render('panels/db/summary', [
            'timings' => $timings,
            'panel' => $this,
            'queryCount' => $queryCount,
            'queryTime' => $queryTime,
            'excessiveCallerCount' => $excessiveCallerCount,
        ]);
    }

    /**
     * @return string short name of the panel, which will be use in summary.
     */
    public function getSummaryName()
    {
        return 'DB';
    }

    public function getToolbarIcon()
    {
        return 'db';
    }

    /**
     * Returns array query types
     *
     * @return array
     * @since 2.0.3
     */
    public function getTypes()
    {
        return array_reduce(
            $this->_models,
            function ($result, $item) {
                $result[$item['type']] = $item['type'];
                return $result;
            },
            [],
        );
    }

    /**
     * Returns the badge variant for a query type.
     *
     * Maps SQL command verbs to a visual class so the queries grid can render a colored pill (info / success / warning /
     * danger / muted) at a glance.
     *
     * @since 2.1.30
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

    public function init()
    {
        $this->actions['db-explain'] = [
            'class' => 'yii\\debug\\actions\\db\\ExplainAction',
            'panel' => $this,
        ];

        // Hook the panel-bound DB component so every prepared statement records its rowCount.
        // We swap PDOStatement subclass via the PDO attribute — works across Yii 2 forks since it
        // does not depend on Connection::$commandClass (which not every fork exposes).
        $db = Yii::$app->get($this->db, false);

        if (!$db instanceof \yii\db\Connection) {
            return;
        }

        $apply = static function (\yii\db\Connection $conn): void {
            $conn->pdo?->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [\yii\debug\db\DebugPdoStatement::class, []]);
        };

        if ($db->pdo !== null) {
            $apply($db);
        }

        $db->on(
            \yii\db\Connection::EVENT_AFTER_OPEN,
            static function (\yii\base\Event $event) use ($apply): void {
                $apply($event->sender);
            },
        );
    }

    public function isEnabled()
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
     * @return bool
     */
    public function isNumberOfCallsExcessive($numCalls)
    {
        return ($this->excessiveCallerThreshold !== null) && ($numCalls > $this->excessiveCallerThreshold);
    }

    /**
     * Check if given queries count is critical according to the settings.
     *
     * @param int $count queries count
     * @return bool
     */
    public function isQueryCountCritical($count)
    {
        return ($this->criticalQueryThreshold !== null) && ($count > $this->criticalQueryThreshold);
    }

    public function save()
    {
        return [
            'messages' => $this->getProfileLogs(),
            'rowCounts' => \yii\debug\db\DebugPdoStatement::$rowCounts,
        ];
    }

    /**
     * Returns sum of all duplicated queries
     *
     * @return int
     * @since 2.0.13
     */
    public function sumDuplicateQueries($modelData)
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
     * Returns an  array of models that represents logs of the current request.
     * Can be used with data providers such as \yii\data\ArrayDataProvider.
     * @return array models
     */
    protected function getModels()
    {
        if ($this->_models === null) {
            $this->_models = [];
            $timings = $this->calculateTimings();
            $duplicates = $this->countDuplicateQuery($timings);
            $rowCounts = $this->data['rowCounts'] ?? \yii\debug\db\DebugPdoStatement::$rowCounts;
            $rowCountIndex = 0;

            foreach ($timings as $seq => $dbTiming) {
                $rows = $rowCounts[$rowCountIndex] ?? null;
                $rowCountIndex++;

                $this->_models[] = [
                    'type' => $this->getQueryType($dbTiming['info']),
                    'query' => $dbTiming['info'],
                    'duration' => ($dbTiming['duration'] * 1000), // in milliseconds
                    'trace' => $dbTiming['trace'],
                    'traceHash' => $dbTiming['traceHash'],
                    'timestamp' => ($dbTiming['timestamp'] * 1000), // in milliseconds
                    'seq' => $seq,
                    'duplicate' => $duplicates[$dbTiming['info']],
                    'rows' => is_int($rows) && $rows >= 0 ? $rows : null,
                ];
            }
        }

        return $this->_models;
    }

    /**
     * Returns database query type.
     *
     * @param string $timing timing procedure string
     * @return string query type such as select, insert, delete, etc.
     */
    protected function getQueryType($timing)
    {
        $timing = ltrim($timing);
        preg_match('/^([a-zA-z]*)/', $timing, $matches);

        return count($matches) ? mb_strtoupper($matches[0], 'utf8') : '';
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function getToolbarItems()
    {
        $timings = $this->calculateTimings();
        $queryCount = count($timings);

        if ($queryCount === 0) {
            return null;
        }

        $warning = '';
        $excessiveCallerCount = $this->getExcessiveCallersCount();

        if ($this->isQueryCountCritical($queryCount)) {
            $warning = "Too many queries, allowed count is {$this->criticalQueryThreshold}.";
        }
        if ($excessiveCallerCount) {
            $warning .= ($warning ? "\n" : '') . $excessiveCallerCount . ' '
                . ($excessiveCallerCount === 1 ? 'caller is' : 'callers are')
                . ' making too many calls.';
        }

        return [
            [
                'value' => $queryCount,
                'status' => $warning ? 'warning' : 'info',
                'title' => $warning ?: "Executed $queryCount database queries.",
            ],
            [
                'value' => number_format($this->getTotalQueryTime($timings) * 1000) . ' ms',
                'title' => 'Total query time',
            ],
        ];
    }

    /**
     * Returns total query time.
     *
     * @param array $timings
     * @return int total time
     */
    protected function getTotalQueryTime($timings)
    {
        $queryTime = 0;

        foreach ($timings as $timing) {
            $queryTime += $timing['duration'];
        }

        return $queryTime;
    }

    /**
     * @throws InvalidConfigException
     * @return bool Whether the DB component has support for EXPLAIN queries
     * @since 2.0.5
     */
    protected function hasExplain()
    {
        try {
            $db = $this->getDb();
        } catch (InvalidConfigException $e) {
            return false;
        }
        if (!($db instanceof \yii\db\Connection)) {
            return false;
        }
        switch ($db->getDriverName()) {
            case 'mysql':
            case 'sqlite':
            case 'pgsql':
            case 'cubrid':
                return true;
            default:
                return false;
        }
    }
}
