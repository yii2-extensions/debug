<?php

declare(strict_types=1);

namespace yii\debug\tests\log;

use PHPForge\Debug\Panel\Log\LogRow;
use PHPUnit\Framework\Attributes\Group;
use yii\data\{Pagination, Sort};
use yii\debug\models\search\LogSearch;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see LogSearch} covering attribute labels, validation rules, and the substring/exact-match dispatch
 * backing the Log panel grid.
 */
#[Group('log')]
#[Group('search')]
final class LogSearchTest extends TestCase
{
    public function testAttributeLabelsCoverEveryFilterField(): void
    {
        $labels = (new LogSearch())->attributeLabels();

        self::assertArrayHasKey(
            'level',
            $labels,
            "'level' label must be defined.",
        );
        self::assertArrayHasKey(
            'category',
            $labels,
            "'category' label must be defined.",
        );
        self::assertArrayHasKey(
            'message',
            $labels,
            "'message' label must be defined.",
        );
        self::assertArrayHasKey(
            'timeSincePrevious',
            $labels,
            "'timeSincePrevious' label must be defined.",
        );
    }

    public function testRulesMarkEveryFilterAsSafe(): void
    {
        self::assertSame(
            [[['level', 'message', 'category'], 'safe']],
            (new LogSearch())->rules(),
            'Every log filter must remain safe for mass assignment.',
        );
    }

    public function testSearchAppliesExactMatchOnLevel(): void
    {
        $this->mockWebApplication();

        $records = [
            self::row(level: 1),
            self::row(level: 10),
            self::row(level: 2),
        ];

        self::assertSame(
            1,
            (new LogSearch())->search(['Log' => ['level' => '1']], $records)->getTotalCount(),
            'Level filter must apply exact match.',
        );
    }

    public function testSearchAppliesPartialMatchOnCategory(): void
    {
        $this->mockWebApplication();

        $records = [
            self::row(category: 'application', message: 'boot'),
            self::row(level: 2, category: 'database', message: 'query'),
            self::row(category: 'app.user', message: 'login'),
        ];

        $search = new LogSearch();

        $provider = $search->search(['Log' => ['category' => 'app']], $records);

        self::assertSame(
            2,
            $provider->getTotalCount(),
            "Substring match on 'app' must surface 'application' and 'app.user'.",
        );
    }

    public function testSearchAppliesPartialMatchOnMessage(): void
    {
        $this->mockWebApplication();

        $records = [
            self::row(message: 'User logged in'),
            self::row(message: 'User logged out'),
            self::row(message: 'Database query'),
        ];

        self::assertSame(
            2,
            (new LogSearch())->search(['Log' => ['message' => 'logged']], $records)->getTotalCount(),
            'Message filter must apply substring match.',
        );
    }

    public function testSearchConfiguresPaginationAndSortOrder(): void
    {
        $this->mockWebApplication();

        $provider = (new LogSearch())->search([], []);

        $pagination = $provider->getPagination();
        $sort = $provider->getSort();

        self::assertInstanceOf(
            Pagination::class,
            $pagination,
            'Pagination must be configured for the log search provider.',
        );
        self::assertSame(
            50,
            $pagination->getPageSize(),
            "Page size must be '50'.",
        );
        self::assertInstanceOf(
            Sort::class,
            $sort,
            'Sort must be configured for the log search provider.',
        );
        self::assertSame(
            ['time', 'timeSincePrevious', 'level', 'category', 'message'],
            array_keys($sort->attributes),
            'Sort must be configured for every filterable attribute.'
        );
        self::assertSame(
            [
                'asc' => ['timeSincePrevious' => SORT_ASC],
                'desc' => ['timeSincePrevious' => SORT_DESC],
                'default' => SORT_DESC,
            ],
            $sort->attributes['timeSincePrevious'] ?? null,
            'Sort must be configured for the timeSincePrevious attribute.',
        );
        self::assertSame(
            ['time' => SORT_ASC],
            $sort->defaultOrder,
            'Default sort order must be by time ascending.',
        );
    }

    public function testSearchReturnsAllRecordsWhenFilterEmpty(): void
    {
        $this->mockWebApplication();

        $records = [
            self::row(category: 'a', message: 'x'),
            self::row(level: 2, category: 'b', message: 'y'),
        ];

        self::assertSame(
            2,
            (new LogSearch())->search([], $records)->getTotalCount(),
            'No filter must keep the full record set.',
        );
    }

    public function testSearchReturnsUnfilteredProviderWhenValidateShortCircuits(): void
    {
        $this->mockWebApplication();

        $records = [
            self::row(category: 'a', message: 'x'),
            self::row(level: 2, category: 'b', message: 'y'),
        ];

        $search = new class extends LogSearch {
            public function beforeValidate(): bool
            {
                return false;
            }
        };

        self::assertSame(
            2,
            $search->search(['Log' => ['category' => 'a']], $records)->getTotalCount(),
            'Failed validation must short-circuit filtering.',
        );
    }

    private static function row(int $level = 1, string $category = 'app', string $message = 'msg'): LogRow
    {
        return new LogRow(
            id: 1,
            message: $message,
            level: $level,
            category: $category,
            time: 0.0,
            timeOfPrevious: 0.0,
            timeSincePrevious: 0.0,
            idOfPrevious: null,
            idOfNext: null,
            memory: 0,
            trace: [],
        );
    }
}
