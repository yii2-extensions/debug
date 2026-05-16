<?php

declare(strict_types=1);

namespace yii\debug\tests\profile;

use PHPUnit\Framework\Attributes\Group;
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
            [
                'category' => 'db',
                'info' => 'SELECT',
                'duration' => 0.1,
                'seq' => 0,
            ],
            [
                'category' => 'app',
                'info' => 'boot',
                'duration' => 0.2,
                'seq' => 1,
            ],
        ];

        $search = new ProfileSearch();

        $provider = $search->search(['ProfileSearch' => ['category' => 'db']], $records);

        self::assertSame(
            1,
            $provider->getTotalCount(),
            "Substring match on 'db' must surface only the database row.",
        );
    }

    public function testSearchReturnsAllRecordsWhenFilterEmpty(): void
    {
        $this->mockWebApplication();

        $records = [
            [
                'category' => 'a',
                'info' => 'x',
                'duration' => 0.1,
                'seq' => 0,
            ],
            [
                'category' => 'b',
                'info' => 'y',
                'duration' => 0.2,
                'seq' => 1,
            ],
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
            [
                'category' => 'a',
                'info' => 'x',
                'duration' => 0.1,
                'seq' => 0,
            ],
            [
                'category' => 'b',
                'info' => 'y',
                'duration' => 0.2,
                'seq' => 1,
            ],
        ];

        $search = new class extends ProfileSearch {
            public function beforeValidate(): bool
            {
                return false;
            }

            public function formName(): string
            {
                return 'ProfileSearch';
            }
        };

        self::assertSame(
            2,
            $search->search(['ProfileSearch' => ['category' => 'a']], $records)->getTotalCount(),
            'Failed validation must short-circuit filtering.',
        );
    }
}
