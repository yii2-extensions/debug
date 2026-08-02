<?php

declare(strict_types=1);

namespace yii\debug\tests\db;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\models\search\DbSearch;
use yii\debug\panels\db\QueryRow;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see DbSearch} covering the filter validation short-circuit branch of `search()`.
 */
#[Group('db')]
#[Group('search')]
final class DbSearchTest extends TestCase
{
    public function testSearchAppliesQueryFilterAcrossSqlText(): void
    {
        $this->mockWebApplication();

        $models = [
            self::row('SELECT', 'SELECT * FROM users'),
            self::row('INSERT', 'INSERT INTO logs VALUES (1)'),
            self::row('SELECT', 'SELECT * FROM posts'),
        ];

        $search = new DbSearch();

        $search->query = 'users';

        $provider = $search->search($models);

        $rows = $provider->allModels;

        self::assertCount(
            1,
            $rows,
            "Filtering on 'users' must return only the matching query row.",
        );

        $first = $rows[0] ?? null;

        self::assertInstanceOf(
            QueryRow::class,
            $first,
            'Surviving row must be the matched query record.',
        );
        self::assertSame(
            'SELECT * FROM users',
            $first->query,
            "Surviving row must carry the matched 'users' query.",
        );
    }

    public function testSearchReturnsAllRowsWhenValidateShortCircuits(): void
    {
        $models = [
            self::row('SELECT', 'SELECT 1'),
            self::row('INSERT', 'INSERT INTO logs VALUES (1)'),
        ];

        $search = new class extends DbSearch {
            public function beforeValidate(): bool
            {
                return false;
            }
        };

        $provider = $search->search($models);

        self::assertSame(
            $models,
            $provider->allModels,
            'Failed validation must short-circuit filtering and return every input row.',
        );
    }

    private static function row(string $type, string $query): QueryRow
    {
        return new QueryRow(
            type: $type,
            query: $query,
            duration: 0.0,
            trace: [],
            traceHash: 'hash',
            timestamp: 0.0,
            seq: 0,
            duplicate: 1,
            rows: null,
        );
    }
}
