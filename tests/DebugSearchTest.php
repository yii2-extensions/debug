<?php

declare(strict_types=1);

namespace yii\debug\tests;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\models\search\DebugSearch;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see DebugSearch} covering attribute labels, validation rules, the `search()` filter pipeline, the
 * `isCodeCritical()` predicate, and the operator-aware `>`/`<` matcher.
 */
#[Group('debug-search')]
final class DebugSearchTest extends TestCase
{
    public function testAttributeLabelsCoverEveryFilterField(): void
    {
        $labels = (new DebugSearch())->attributeLabels();

        self::assertArrayHasKey(
            'tag',
            $labels,
            "'tag' label must be defined.",
        );
        self::assertArrayHasKey(
            'processingTime',
            $labels,
            "'processingTime' label must be defined.",
        );
        self::assertArrayHasKey(
            'peakMemory',
            $labels,
            "'peakMemory' label must be defined.",
        );
        self::assertArrayHasKey(
            'ip',
            $labels,
            "'ip' label must be defined.",
        );
        self::assertArrayHasKey(
            'method',
            $labels,
            "'method' label must be defined.",
        );
        self::assertArrayHasKey(
            'ajax',
            $labels,
            "'ajax' label must be defined.",
        );
        self::assertArrayHasKey(
            'url',
            $labels,
            "'url' label must be defined.",
        );
        self::assertArrayHasKey(
            'statusCode',
            $labels,
            "'statusCode' label must be defined.",
        );
        self::assertArrayHasKey(
            'sqlCount',
            $labels,
            "'sqlCount' label must be defined.",
        );
        self::assertArrayHasKey(
            'mailCount',
            $labels,
            "'mailCount' label must be defined.",
        );
    }
    public function testFilterRejectsMalformedInternalConditions(): void
    {
        $this->mockWebApplication();

        $search = new DebugSearch();

        $this->setInaccessibleProperty(
            $search,
            'conditions',
            [['attribute' => 'value', 'operator' => '>', 'value' => '5']],
        );

        self::assertSame(
            [],
            $this->invoke($search, 'filter', [[['value' => 6]]]),
            'Numeric conditions with a non-float boundary must reject the row.',
        );

        $this->setInaccessibleProperty(
            $search,
            'conditions',
            [['attribute' => 'value', 'operator' => 'same', 'value' => 5.0]],
        );

        self::assertSame(
            [],
            $this->invoke($search, 'filter', [[['value' => '5']]]),
            'Text conditions with a non-string boundary must reject the row.',
        );
    }

    public function testIsCodeCriticalFlagsConfiguredHttpStatusCodes(): void
    {
        $search = new DebugSearch();

        self::assertTrue(
            $search->isCodeCritical(500),
            'Server errors must be flagged as critical.',
        );
        self::assertTrue(
            $search->isCodeCritical(404),
            'Not-found responses must be flagged as critical.',
        );
        self::assertFalse(
            $search->isCodeCritical(200),
            'Successful responses must not be flagged as critical.',
        );
    }

    public function testRulesDeclareAllFilterAttributesAsSafe(): void
    {
        $rules = (new DebugSearch())->rules();

        $firstRule = $rules[0] ?? null;

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

    public function testSearchAppliesGreaterThanOperatorOnSqlCount(): void
    {
        $this->mockWebApplication();

        $records = [
            ['method' => 'GET', 'sqlCount' => 2, 'mailCount' => 0],
            ['method' => 'GET', 'sqlCount' => 10, 'mailCount' => 0],
            ['method' => 'POST', 'sqlCount' => 20, 'mailCount' => 0],
        ];

        $search = new DebugSearch();

        $provider = $search->search(['DebugSearch' => ['sqlCount' => '>5']], $records);

        self::assertSame(
            2,
            $provider->getTotalCount(),
            "'>5' must match records with 'sqlCount' strictly greater than five.",
        );
    }

    public function testSearchAppliesLowerThanOperatorOnMailCount(): void
    {
        $this->mockWebApplication();

        $records = [
            ['method' => 'GET', 'sqlCount' => 1, 'mailCount' => 1],
            ['method' => 'GET', 'sqlCount' => 1, 'mailCount' => 5],
            ['method' => 'POST', 'sqlCount' => 1, 'mailCount' => 10],
        ];

        $search = new DebugSearch();

        $provider = $search->search(['DebugSearch' => ['mailCount' => '<5']], $records);

        self::assertSame(
            1,
            $provider->getTotalCount(),
            "'<5' must match records with 'mailCount' strictly lower than five.",
        );
    }

    public function testSearchAppliesPartialMatchOnIp(): void
    {
        $this->mockWebApplication();

        $records = [
            ['method' => 'GET', 'ip' => '127.0.0.1', 'sqlCount' => 0, 'mailCount' => 0],
            ['method' => 'GET', 'ip' => '10.0.0.1', 'sqlCount' => 0, 'mailCount' => 0],
            ['method' => 'GET', 'ip' => '192.168.1.1', 'sqlCount' => 0, 'mailCount' => 0],
        ];

        $search = new DebugSearch();

        $provider = $search->search(['DebugSearch' => ['ip' => '10.']], $records);

        self::assertSame(
            1,
            $provider->getTotalCount(),
            "Substring match on '10.' must surface only the '10.0.0.1' entry.",
        );
    }

    public function testSearchDoesNotParseEmbeddedOperatorsAsComparisons(): void
    {
        $this->mockWebApplication();

        $records = [
            ['url' => '/report >5 ms', 'sqlCount' => 0, 'mailCount' => 0],
            ['url' => '/report/10', 'sqlCount' => 0, 'mailCount' => 0],
        ];

        $provider = (new DebugSearch())->search(['DebugSearch' => ['url' => 'report >5']], $records);

        self::assertSame(1, $provider->getTotalCount(), 'Partial text fields must treat embedded operators as text.');
    }

    public function testSearchRejectsNonNumericCandidatesForNumericConditions(): void
    {
        $this->mockWebApplication();

        $records = [
            ['sqlCount' => 'not-numeric', 'mailCount' => 0],
            ['sqlCount' => [], 'mailCount' => 0],
            ['sqlCount' => 6, 'mailCount' => 0],
        ];

        $provider = (new DebugSearch())->search(['DebugSearch' => ['sqlCount' => '>5']], $records);

        self::assertSame(1, $provider->getTotalCount(), 'Numeric comparisons must reject non-numeric candidates.');
    }

    public function testSearchRejectsRowsWithoutTheFilteredAttribute(): void
    {
        $this->mockWebApplication();

        $records = [
            ['method' => 'GET', 'sqlCount' => 0, 'mailCount' => 0],
            ['sqlCount' => 0, 'mailCount' => 0],
        ];

        $provider = (new DebugSearch())->search(['DebugSearch' => ['method' => 'GET']], $records);

        self::assertSame(1, $provider->getTotalCount(), 'Rows without the filtered attribute must not match.');
    }

    public function testSearchReturnsAllRowsWhenValidateShortCircuits(): void
    {
        $this->mockWebApplication();

        $records = [
            ['method' => 'GET', 'sqlCount' => 1, 'mailCount' => 0],
            ['method' => 'POST', 'sqlCount' => 2, 'mailCount' => 0],
        ];

        $search = new class extends DebugSearch {
            public function beforeValidate(): bool
            {
                return false;
            }

            public function formName(): string
            {
                return 'DebugSearch';
            }
        };

        $provider = $search->search(['DebugSearch' => ['method' => 'GET']], $records);

        self::assertSame(
            2,
            $provider->getTotalCount(),
            'Failed validation must short-circuit filtering and keep every record.',
        );
    }

    public function testSearchReturnsUnfilteredProviderWhenParamsEmpty(): void
    {
        $this->mockWebApplication();

        $records = [
            ['method' => 'GET', 'sqlCount' => 1, 'mailCount' => 0],
            ['method' => 'POST', 'sqlCount' => 2, 'mailCount' => 0],
        ];

        $search = new DebugSearch();

        $provider = $search->search([], $records);

        self::assertSame(
            2,
            $provider->getTotalCount(),
            'Empty filter params must yield the full record set.',
        );
    }

    public function testSearchTreatsZeroAsAValidNumericBoundary(): void
    {
        $this->mockWebApplication();

        $records = [
            ['sqlCount' => 0, 'mailCount' => 0],
            ['sqlCount' => 1, 'mailCount' => 0],
        ];

        $provider = (new DebugSearch())->search(['DebugSearch' => ['sqlCount' => '>0']], $records);

        self::assertSame(1, $provider->getTotalCount(), "'>0' must retain only positive values.");
    }
}
