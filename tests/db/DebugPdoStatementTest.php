<?php

declare(strict_types=1);

namespace yii\debug\tests\db;

use PDO;
use PHPUnit\Framework\Attributes\Group;
use yii\debug\db\DebugPdoStatement;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see DebugPdoStatement} covering the row-count capture hook invoked after every prepared
 * statement execution.
 */
#[Group('db')]
final class DebugPdoStatementTest extends TestCase
{
    public function testExecuteAppendsRowCountAfterPreparedStatementRuns(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [DebugPdoStatement::class]);
        $pdo->exec('CREATE TABLE rowcounts (id INTEGER PRIMARY KEY, label TEXT NOT NULL)');

        $insert = $pdo->prepare('INSERT INTO rowcounts (label) VALUES (:label)');

        self::assertInstanceOf(
            DebugPdoStatement::class,
            $insert,
            "'PDO::prepare()' must return a 'DebugPdoStatement' via 'ATTR_STATEMENT_CLASS'.",
        );
        self::assertTrue(
            $insert->execute([':label' => 'first']),
            'Prepared INSERT must succeed against the in-memory SQLite fixture.',
        );
        self::assertTrue(
            $insert->execute([':label' => 'second']),
            'Second INSERT must also succeed.',
        );

        $rowCounts = DebugPdoStatement::$rowCounts;
        DebugPdoStatement::$rowCounts = [];

        self::assertCount(
            2,
            $rowCounts,
            'Every execute() call must append exactly one rowCount entry.',
        );
        self::assertSame(
            [1, 1],
            $rowCounts,
            'Each INSERT must record `1` rows affected.',
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        DebugPdoStatement::$rowCounts = [];
    }
}
