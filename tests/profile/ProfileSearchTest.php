<?php

declare(strict_types=1);

namespace yii\debug\tests\profile;

use PHPForge\Debug\Panel\Profile\ProfileRow;
use PHPUnit\Framework\Attributes\Group;
use yii\data\{Pagination, Sort};
use yii\debug\models\search\ProfileSearch;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see ProfileSearch} covering attribute labels, validation rules, and the substring-match dispatch
 * backing the Profiling panel grid.
 */
#[Group('profile')]
#[Group('search')]
final class ProfileSearchTest extends TestCase
{
    public function testAttributeLabelsCoverEveryFilterField(): void
    {
        $labels = (new ProfileSearch())->attributeLabels();

        self::assertArrayHasKey(
            'category',
            $labels,
            "'category' label must be defined.",
        );
        self::assertArrayHasKey(
            'info',
            $labels,
            "'info' label must be defined.",
        );
    }

    public function testRulesMarkEveryFilterAsSafe(): void
    {
        $firstRule = (new ProfileSearch())->rules()[0] ?? null;

        self::assertIsArray(
            $firstRule,
            'First rule must be a configuration tuple.',
        );
        self::assertSame(
            'safe',
            $firstRule[1] ?? null,
            "First rule must mark filter fields as 'safe'.",
        );
    }

    public function testSearchAppliesPartialMatchOnCategory(): void
    {
        $this->mockWebApplication();

        $records = [
            self::block('db', 'SELECT', 0.1, 0),
            self::block('app', 'boot', 0.2, 1),
        ];

        $search = new ProfileSearch();

        $provider = $search
            ->search(['Profile' => ['category' => 'db']], $records);

        self::assertSame(
            1,
            $provider->getTotalCount(),
            "Substring match on 'db' must surface only the database row.",
        );
    }

    public function testSearchConfiguresPaginationAndSortingContracts(): void
    {
        $this->mockWebApplication();

        $provider = (new ProfileSearch())->search([], []);

        $pagination = $provider->getPagination();
        $sort = $provider->getSort();

        self::assertInstanceOf(
            Pagination::class,
            $pagination,
            'Profiling pagination must be enabled.',
        );
        self::assertSame(
            50,
            $pagination->pageSize,
            'Profiling pagination must default to fifty rows.',
        );
        self::assertInstanceOf(
            Sort::class,
            $sort,
            'Profiling sorting must be enabled.',
        );
        self::assertSame(
            ['category', 'seq', 'duration', 'info'],
            array_keys($sort->attributes),
            'Every displayed profiling field must remain sortable.',
        );
        self::assertSame(
            ['duration' => SORT_DESC],
            $sort->defaultOrder,
            'Profiling rows must sort by longest duration first.',
        );
    }

    public function testSearchReturnsAllRecordsWhenFilterEmpty(): void
    {
        $this->mockWebApplication();

        $records = [
            self::block('a', 'x', 0.1, 0),
            self::block('b', 'y', 0.2, 1),
        ];

        self::assertSame(
            2,
            (new ProfileSearch())->search([], $records)->getTotalCount(),
            'No filter must keep the full record set.',
        );
    }

    public function testSearchReturnsUnfilteredProviderWhenValidateShortCircuits(): void
    {
        $this->mockWebApplication();

        $records = [
            self::block('a', 'x', 0.1, 0),
            self::block('b', 'y', 0.2, 1),
        ];

        $search = new class extends ProfileSearch {
            public function beforeValidate(): bool
            {
                return false;
            }
        };

        self::assertSame(
            2,
            $search
                ->search(['Profile' => ['category' => 'a']], $records)
                ->getTotalCount(),
            'Failed validation must short-circuit filtering.',
        );
    }

    public function testSearchUsesSubstringMatchingForCategoryAndInfo(): void
    {
        $this->mockWebApplication();

        $records = [
            self::block('yii\\db', 'SELECT users', 0.1, 0),
            self::block('app', 'boot', 0.2, 1),
        ];

        self::assertSame(
            1,
            (new ProfileSearch())
                ->search(['Profile' => ['category' => 'ii\\d']], $records)
                ->getTotalCount(),
            'Category filtering must use substring matching.',
        );
        self::assertSame(
            1,
            (new ProfileSearch())
                ->search(['Profile' => ['info' => 'LECT']], $records)
                ->getTotalCount(),
            'Info filtering must use substring matching.',
        );
    }

    private static function block(string $category, string $info, float $duration, int $seq): ProfileRow
    {
        return new ProfileRow(0.0, $duration, $category, $info, 0, $seq, 0, 0, []);
    }
}
