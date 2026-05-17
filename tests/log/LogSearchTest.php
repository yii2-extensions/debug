<?php

declare(strict_types=1);

namespace yii\debug\tests\log;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\models\search\LogSearch;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see LogSearch} covering attribute labels, validation rules, and the substring/exact-match
 * dispatch backing the Log panel grid.
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
            'time_since_previous',
            $labels,
            "'time_since_previous' label must be defined.",
        );
    }

    public function testRulesMarkEveryFilterAsSafe(): void
    {
        $firstRule = (new LogSearch())->rules()[0] ?? null;

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
                'level' => '1',
                'category' => 'application',
                'message' => 'boot',
            ],
            [
                'level' => '2',
                'category' => 'database',
                'message' => 'query',
            ],
            [
                'level' => '1',
                'category' => 'app.user',
                'message' => 'login',
            ],
        ];

        $search = new LogSearch();

        $provider = $search->search(['LogSearch' => ['category' => 'app']], $records);

        self::assertSame(
            2,
            $provider->getTotalCount(),
            "Substring match on 'app' must surface 'application' and 'app.user'.",
        );
    }

    public function testSearchReturnsAllRecordsWhenFilterEmpty(): void
    {
        $this->mockWebApplication();

        $records = [
            [
                'level' => '1',
                'category' => 'a',
                'message' => 'x',
            ],
            [
                'level' => '2',
                'category' => 'b',
                'message' => 'y',
            ],
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
            [
                'level' => '1',
                'category' => 'a',
                'message' => 'x',
            ],
            [
                'level' => '2',
                'category' => 'b',
                'message' => 'y',
            ],
        ];

        $search = new class extends LogSearch {
            public function beforeValidate(): bool
            {
                return false;
            }

            public function formName(): string
            {
                return 'LogSearch';
            }
        };

        self::assertSame(
            2,
            $search->search(['LogSearch' => ['category' => 'a']], $records)->getTotalCount(),
            'Failed validation must short-circuit filtering.',
        );
    }
}
