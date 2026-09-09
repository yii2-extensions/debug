<?php

declare(strict_types=1);

namespace yii\debug\tests\db;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use yii\debug\db\DebugPdoStatement;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see DebugPdoStatement} covering the row-count capture hook invoked after every prepared statement
 * execution.
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
            'Prepared statements must use the debug wrapper.',
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
            'One row-count entry must be appended.',
        );
        self::assertSame(
            [1, 1],
            $rowCounts,
            'Each INSERT must record `1` rows affected.',
        );
    }

    public function testExecuteKeepsSequenceAlignmentWhenAStatementFails(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [DebugPdoStatement::class]);
        $pdo->exec('CREATE TABLE rowcounts (id INTEGER PRIMARY KEY, label TEXT NOT NULL)');

        $failing = $pdo->prepare('INSERT INTO rowcounts (label) VALUES (:label)');

        self::assertInstanceOf(
            DebugPdoStatement::class,
            $failing,
            'Prepared statements must use the debug wrapper.',
        );

        try {
            $failing->execute([':label' => null]);

            self::fail('The NOT NULL constraint must reject the statement.');
        } catch (PDOException) {
            // The rethrown driver failure is the trigger; the recorded slot is asserted below.
        }

        $succeeding = $pdo->prepare('INSERT INTO rowcounts (label) VALUES (:label)');

        self::assertInstanceOf(
            DebugPdoStatement::class,
            $succeeding,
            'The follow-up statement must use the debug wrapper too.',
        );
        self::assertTrue(
            $succeeding->execute([':label' => 'second']),
            'The statement after the failure must still run.',
        );

        $rowCounts = DebugPdoStatement::$rowCounts;
        DebugPdoStatement::$rowCounts = [];

        self::assertSame(
            [null, 1],
            $rowCounts,
            'A failed statement must hold its slot so later counts stay aligned.',
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        DebugPdoStatement::$rowCounts = [];
    }
}
