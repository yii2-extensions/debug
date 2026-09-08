<?php

declare(strict_types=1);

namespace yii\debug\db;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Records the row count produced by every executed prepared statement.
 *
 * The Yii profiler captures each query's SQL and timing but discards the {@see PDOStatement}, so the row count is
 * unrecoverable downstream. This subclass hooks {@see PDOStatement::execute()} to read `rowCount()` right after
 * execution and append it to a request-scoped list in execution order.
 *
 * Registered by {@see \yii\debug\collectors\DbCollector} via {@see PDO::ATTR_STATEMENT_CLASS} on the collector-bound DB
 * connection, so every prepared statement returned by the underlying PDO instance is one of these.
 */
class DebugPdoStatement extends PDOStatement
{
    /**
     * @var list<int|null> Row count per executed statement, appended in execution order; `null` marks a statement whose
     * execution threw, so later statements keep their sequence position.
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
     * A statement that throws still occupies a slot, holding `null`, because the Database panel aligns the list with
     * the trailing captured timings: skipping the failed statement would shift every later row count onto the wrong
     * query.
     *
     * @param array<int|string, mixed>|null $params Values bound to the statement placeholders, if any.
     *
     * @throws PDOException When the driver rejects the statement in `PDO::ERRMODE_EXCEPTION` mode.
     *
     * @return bool `true` on success, `false` on failure.
     */
    public function execute(array|null $params = null): bool
    {
        try {
            $result = parent::execute($params);
        } catch (PDOException $exception) {
            self::$rowCounts[] = null;

            throw $exception;
        }

        self::$rowCounts[] = $this->rowCount();

        return $result;
    }
}
