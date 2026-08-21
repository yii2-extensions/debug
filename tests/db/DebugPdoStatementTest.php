<?php

declare(strict_types=1);

namespace yii\debug\tests\db;

use PDO;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use yii\debug\db\DebugPdoStatement;
use yii\debug\tests\provider\VisibilityProvider;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see DebugPdoStatement} covering the row-count capture hook invoked after every prepared statement
 * execution.
 *
 * {@see VisibilityProvider} for method contract data providers.
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

    /**
     * @param class-string $class
     * @param 'protected'|'public' $expected
     */
    #[DataProviderExternal(VisibilityProvider::class, 'debugPdoStatementContracts')]
    public function testExtensionMethodKeepsDeclaredVisibility(string $class, string $method, string $expected): void
    {
        self::assertMethodVisibility($class, $method, $expected);
    }

    protected function setUp(): void
    {
        parent::setUp();

        DebugPdoStatement::$rowCounts = [];
    }
}
