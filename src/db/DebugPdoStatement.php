<?php

declare(strict_types=1);

namespace yii\debug\db;

use PDOStatement;

/**
 * PDOStatement subclass that records the row count of every executed query.
 *
 * Yii profiler captures the SQL string and timing of each query but discards the {@see PDOStatement}, so the row count
 * is unrecoverable downstream. This class hooks {@see PDOStatement::execute()} to read `rowCount()` immediately after
 * execution and append it to a request-scoped list, in execution order.
 *
 * Used by {@see \yii\debug\panels\DbPanel} which registers it via `PDO::ATTR_STATEMENT_CLASS` on the panel-bound DB
 * connection so every prepared statement returned by the underlying PDO instance is one of these.
 */
class DebugPdoStatement extends PDOStatement
{
    /**
     * @var array<int, int> Row count per executed query, pushed in execution order.
     */
    public static array $rowCounts = [];

    /**
     * Required by PHP {@see PDO::ATTR_STATEMENT_CLASS} demands the constructor be `protected` (or `private`) so callers
     * cannot instantiate the class outside of the PDO factory.
     */
    protected function __construct() {}

    /**
     * Overrides the parent method to capture the row count immediately after execution and append it to the static
     * list.
     *
     * @param array<int|string, mixed>|null $params Parameters to bind to the query, if any.
     *
     * @return bool `true` on success or `false` on failure.
     */
    public function execute(array|null $params = null): bool
    {
        $result = parent::execute($params);

        self::$rowCounts[] = $this->rowCount();

        return $result;
    }
}
