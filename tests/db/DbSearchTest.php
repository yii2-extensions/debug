<?php

declare(strict_types=1);

namespace yii\debug\tests\db;

use PHPForge\Debug\Panel\Db\QueryRow;
use PHPUnit\Framework\Attributes\Group;
use yii\data\{Pagination, Sort};
use yii\debug\models\search\DbSearch;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see DbSearch} covering the filter validation short-circuit branch of `search()`.
 */
#[Group('db')]
#[Group('search')]
final class DbSearchTest extends TestCase
{
    public function testAttributeLabelsAndRulesExposeTheCompleteFilterContract(): void
    {
        $search = new DbSearch();

        self::assertSame(
            ['type' => 'Type', 'query' => 'Query'],
            $search->attributeLabels(),
            'Both database filter labels must remain available.',
        );
        self::assertSame(
            [[['type', 'query'], 'safe']],
            $search->rules(),
            'Both database filters must remain safe for mass assignment.',
        );
    }

    public function testSearchAppliesPartialMatchOnQueryType(): void
    {
        $this->mockWebApplication();

        $search = new DbSearch();

        $search->type = 'sel';

        $rows = $search->search(
            [
                self::row('SELECT', 'SELECT 1'),
                self::row('INSERT', 'INSERT INTO logs VALUES (1)'),
            ],
        )->allModels;

        self::assertCount(
            1,
            $rows,
            'A partial type filter must keep only the matching statement.',
        );

        $first = $rows[0] ?? null;

        self::assertInstanceOf(
            QueryRow::class,
            $first,
            'The filtered model must remain a query row.',
        );
        self::assertSame(
            'SELECT',
            $first->type,
            'The matching query type must be retained.',
        );
    }

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

    public function testSearchConfiguresEverySortableDatabaseField(): void
    {
        $this->mockWebApplication();

        $sort = (new DbSearch())->search([])->getSort();

        self::assertInstanceOf(
            Sort::class,
            $sort,
            'Database query sorting must be enabled.',
        );
        self::assertSame(
            ['duration', 'seq', 'type', 'query', 'duplicate', 'rows'],
            array_keys($sort->attributes),
            'Every displayed database field must remain sortable.',
        );
    }

    public function testSearchPaginatesDatabaseRowsWithTheSharedDefault(): void
    {
        $this->mockWebApplication();

        $pagination = (new DbSearch())->search([])->getPagination();

        self::assertInstanceOf(
            Pagination::class,
            $pagination,
            'Database query pagination must be enabled.',
        );
        self::assertSame(
            50,
            $pagination->getPageSize(),
            'Database queries must use the shared 50-row default.',
        );
    }

    public function testSearchReturnsAllRowsWhenValidateShortCircuits(): void
    {
        $this->mockWebApplication();

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

        $search->type = 'SELECT';

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
