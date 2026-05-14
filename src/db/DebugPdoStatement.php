<?php

declare(strict_types=1);

namespace yii\debug\db;

use PDO;
use PDOStatement;

/**
 * Records the row count produced by every executed prepared statement.
 *
 * The Yii profiler captures each query's SQL and timing but discards the {@see PDOStatement}, so the row count is
 * unrecoverable downstream. This subclass hooks {@see PDOStatement::execute()} to read `rowCount()` right after
 * execution and append it to a request-scoped list in execution order.
 *
 * Registered by {@see \yii\debug\panels\DbPanel} via {@see PDO::ATTR_STATEMENT_CLASS} on the panel-bound DB connection,
 * so every prepared statement returned by the underlying PDO instance is one of these.
 */
class DebugPdoStatement extends PDOStatement
{
    /**
     * @var array<int, int> Row count per executed statement, appended in execution order.
     */
    public static array $rowCounts = [];

    /**
     * Visibility is `protected` because {@see PDO::ATTR_STATEMENT_CLASS} requires it; callers must not instantiate the
     * class outside of the PDO factory.
     */
    protected function __construct() {}

    /**
     * Executes the prepared statement and records its row count in {@see self::$rowCounts}.
     *
     * @param array<int|string, mixed>|null $params Values bound to the statement placeholders, if any.
     *
     * @return bool `true` on success, `false` on failure.
     */
    public function execute(array|null $params = null): bool
    {
        $result = parent::execute($params);

        self::$rowCounts[] = $this->rowCount();

        return $result;
    }
}
