<?php

declare(strict_types=1);

namespace yii\debug\tests\log;

use PHPForge\Debug\Panel\Log\LogRow;
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
            'timeSincePrevious',
            $labels,
            "'timeSincePrevious' label must be defined.",
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
