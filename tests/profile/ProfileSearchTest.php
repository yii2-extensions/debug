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
        self::assertArrayHasKey(
            'duration',
            $labels,
            "'duration' label must be defined.",
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
        self::assertSame(
            ['category', 'duration', 'info'],
            $firstRule[0] ?? null,
            'The safe rule must cover every Profiling filter field.',
        );
    }

    public function testSearchAppliesInclusiveMinimumDurationWithOtherFilters(): void
    {
        $this->mockWebApplication();

        $records = [
            self::block('yii\\db', 'SELECT users', 1.4, 0),
            self::block('yii\\db', 'SELECT posts', 1.5, 1),
            self::block('yii\\db', 'UPDATE posts', 3.0, 2),
            self::block('app', 'SELECT cache', 4.0, 3),
        ];

        $provider = (new ProfileSearch())->search(
            [
                'Profile' => [
                    'category' => 'db',
                    'duration' => '1.5',
                    'info' => 'select',
                ],
            ],
            $records,
        );

        self::assertSame(
            [$records[1]],
            $provider->allModels,
            'Minimum duration must be inclusive and compose with category and info substring filters.',
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

    public function testSearchIgnoresInvalidDurationWithoutDiscardingOtherFilters(): void
    {
        $this->mockWebApplication();

        $records = [
            self::block('yii\\db', 'SELECT users', 1.0, 0),
            self::block('app', 'boot', 2.0, 1),
        ];

        foreach (['invalid', '-1', '1e309'] as $duration) {
            $search = new ProfileSearch();
            $provider = $search->search(
                ['Profile' => ['category' => 'db', 'duration' => $duration]],
                $records,
            );

            self::assertSame(
                [$records[0]],
                $provider->allModels,
                "Invalid duration '{$duration}' must be ignored while the valid category filter remains active.",
            );
            self::assertSame(
                '',
                $search->duration,
                "Invalid duration '{$duration}' must not remain in the rendered filter input.",
            );
        }
    }

    public function testSearchIgnoresNestedFilterValuesWithoutTypeErrors(): void
    {
        $this->mockWebApplication();

        $records = [
            self::block('yii\\db', 'SELECT users', 1.0, 0),
            self::block('app', 'boot', 2.0, 1),
        ];
        $search = new ProfileSearch();

        $provider = $search->search(
            [
                'Profile' => [
                    'category' => ['db'],
                    'duration' => ['1'],
                    'info' => ['SELECT'],
                ],
            ],
            $records,
        );

        self::assertSame(
            $records,
            $provider->allModels,
            'Nested query values must be ignored instead of being assigned to typed search properties.',
        );
        self::assertSame(
            '',
            $search->category,
            'A nested category must normalize to an empty filter.',
        );
        self::assertSame(
            '',
            $search->duration,
            'A nested duration must normalize to an empty filter.',
        );
        self::assertSame(
            '',
            $search->info,
            'Nested profiling info must normalize to an empty filter.',
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
