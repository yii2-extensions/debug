<?php

declare(strict_types=1);

namespace yii\debug\tests\db;

use PHPForge\Debug\Panel\Db\QueryRow;
use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\data\{Pagination, Sort};
use yii\debug\models\search\{DbSearch, QueryRowDataProvider};
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

    public function testQueryRowProviderPreservesBaseArraySorting(): void
    {
        $this->mockWebApplication();

        $provider = new QueryRowDataProvider(
            [
                'allModels' => [['duration' => 2], ['duration' => 1]],
                'pagination' => false,
                'sort' => ['attributes' => ['duration'], 'defaultOrder' => ['duration' => SORT_ASC]],
            ],
        );

        self::assertSame(
            [['duration' => 1], ['duration' => 2]],
            $provider->getModels(),
            'Supporting private query rows must preserve the inherited array-model sorting contract.',
        );
    }

    public function testSearchAppliesPartialMatchOnQueryType(): void
    {
        $this->mockWebApplication();

        $search = new DbSearch();

        $search->type = 'sel';

        $rows = $search->search(
            [
                self::row(
                    'SELECT',
                    'SELECT 1',
                ),
                self::row(
                    'INSERT',
                    'INSERT INTO logs VALUES (1)',
                ),
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
            $first->getType(),
            'The matching query type must be retained.',
        );
    }

    public function testSearchAppliesQueryFilterAcrossSqlText(): void
    {
        $this->mockWebApplication();

        $models = [
            self::row(
                'SELECT',
                'SELECT * FROM users',
            ),
            self::row(
                'INSERT',
                'INSERT INTO logs VALUES (1)',
            ),
            self::row(
                'SELECT',
                'SELECT * FROM posts',
            ),
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
            $first->getQuery(),
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

    public function testSearchDropsThePageCursorFromSortLinks(): void
    {
        $this->mockWebApplication();

        Yii::$app->requestedRoute = 'debug/view';

        $_GET = ['page' => '3', 'sort' => 'seq'];

        $sort = (new DbSearch())->search([])->getSort();

        self::assertInstanceOf(
            Sort::class,
            $sort,
            'Database query sorting must be enabled.',
        );
        self::assertSame(
            ['seq' => SORT_ASC],
            $sort->getAttributeOrders(),
            'The active ordering must still come from the request.',
        );

        $url = $sort->createUrl('duration');

        self::assertStringNotContainsString(
            'page=',
            $url,
            'Sorting must land on page one.',
        );
        self::assertStringContainsString(
            'sort=duration',
            $url,
            'The link must switch to the clicked attribute.',
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
            self::row(
                'SELECT',
                'SELECT 1',
            ),
            self::row(
                'INSERT',
                'INSERT INTO logs VALUES (1)',
            ),
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

    public function testSearchSortsEveryPrivateFieldInBothDirections(): void
    {
        $this->mockWebApplication();

        $first = QueryRow::create('SELECT z', 3.0, 1000.0)
            ->withSequence(2)
            ->withDuplicate(3);
        $second = QueryRow::create('DELETE a', 1.0, 1000.0)->withRows(0);
        $third = QueryRow::create('UPDATE m', 2.0, 1000.0)
            ->withSequence(1)
            ->withDuplicate(2)
            ->withRows(5);
        $cases = [
            [
                'duration', SORT_ASC,
                [
                    $second,
                    $third,
                    $first,
                ],
            ],
            [
                'duration',
                SORT_DESC,
                [
                    $first,
                    $third,
                    $second,
                ],
            ],
            [
                'seq', SORT_ASC,
                [
                    $second,
                    $third,
                    $first,
                ],
            ],
            [
                'seq',
                SORT_DESC,
                [
                    $first,
                    $third,
                    $second,
                ],
            ],
            [
                'type', SORT_ASC,
                [
                    $second,
                    $first,
                    $third,
                ],
            ],
            [
                'type', SORT_DESC,
                [
                    $third,
                    $first,
                    $second,
                ],
            ],
            [
                'query', SORT_ASC,
                [
                    $second,
                    $first,
                    $third,
                ],
            ],
            [
                'query', SORT_DESC,
                [
                    $third,
                    $first,
                    $second,
                ],
            ],
            [
                'duplicate', SORT_ASC,
                [
                    $second,
                    $third,
                    $first,
                ],
            ],
            [
                'duplicate', SORT_DESC,
                [
                    $first,
                    $third,
                    $second,
                ],
            ],
            [
                'rows', SORT_ASC,
                [
                    $first,
                    $second,
                    $third,
                ],
            ],
            [
                'rows', SORT_DESC,
                [
                    $third,
                    $first,
                    $second,
                ],
            ],
        ];

        foreach ($cases as [$attribute, $direction, $expected]) {
            $provider = (new DbSearch())->search([$first, $second, $third]);

            $sort = $provider->getSort();

            self::assertInstanceOf(
                Sort::class,
                $sort,
                'Query-row sorting must be enabled.',
            );

            $sort->setAttributeOrders([$attribute => $direction]);

            self::assertSame(
                $expected,
                $provider->getModels(),
                "Sorting '{$attribute}' must preserve row identity and stable ordering for equal values.",
            );
        }
    }

    public function testSearchSortsMultipleFieldsBeforePagination(): void
    {
        $this->mockWebApplication();

        $first = QueryRow::create('SELECT 1', 1.0, 1000.0)->withSequence(2);
        $second = QueryRow::create('SELECT 2', 1.0, 1000.0)->withSequence(1);
        $third = QueryRow::create('SELECT 3', 2.0, 1000.0)->withSequence(0);

        $provider = (new DbSearch())->search([$first, $second, $third]);

        $sort = $provider->getSort();
        $pagination = $provider->getPagination();

        self::assertInstanceOf(
            Sort::class,
            $sort,
            'Query-row sorting must be enabled.',
        );
        self::assertInstanceOf(
            Pagination::class,
            $pagination,
            'Query-row pagination must be enabled.',
        );

        $sort->enableMultiSort = true;

        $sort->setAttributeOrders(['duration' => SORT_ASC, 'seq' => SORT_ASC]);
        $pagination->setPageSize(1);
        $pagination->setPage(1, false);

        self::assertSame(
            [1 => $first],
            $provider->getModels(),
            'Pagination must preserve the sorted row key and apply secondary ordering before slicing.',
        );
    }

    private static function row(string $type, string $query): QueryRow
    {
        return QueryRow::create($query, 0.0, 0.0)
            ->withType($type)
            ->withTraceHash('hash');
    }
}
