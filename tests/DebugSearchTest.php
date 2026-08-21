<?php

declare(strict_types=1);

namespace yii\debug\tests;

use PHPForge\Debug\Data\FilterEngine;
use PHPForge\Debug\Storage\RequestSummary;
use PHPUnit\Framework\Attributes\Group;
use yii\data\{Pagination, Sort};
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
        $engine = new FilterEngine();

        $this->setInaccessibleProperty(
            $engine,
            'conditions',
            [['attribute' => 'value', 'operator' => '>', 'value' => '5']],
        );
        $this->setInaccessibleProperty(
            $search,
            'filterEngine',
            $engine,
        );

        self::assertSame(
            [],
            $this->invoke($search, 'filter', [[['value' => 6]]]),
            'Numeric conditions with a non-float boundary must reject the row.',
        );

        $this->setInaccessibleProperty(
            $engine,
            'conditions',
            [['attribute' => 'value', 'operator' => 'same', 'value' => 5.0]],
        );

        self::assertSame(
            [],
            $this->invoke($search, 'filter', [[['value' => '5']]]),
            'Text conditions with a non-string boundary must reject the row.',
        );
    }

    public function testFormNameUsesTheShortDebugPrefix(): void
    {
        $search = new DebugSearch();

        self::assertSame(
            'Debug',
            $search->formName(),
            "Filter params must use the 'Debug' prefix.",
        );
        self::assertTrue(
            $search->load(['Debug' => ['statusCode' => '404']]),
            'The status-pill deep-link prefix must load into the model.',
        );
        self::assertSame(
            '404',
            $search->statusCode,
            'Loaded status code must land on the attribute.',
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
        self::assertSame(
            [
                [['tag', 'ip', 'method', 'ajax', 'url', 'statusCode', 'sqlCount', 'mailCount'], 'safe'],
            ],
            (new DebugSearch())->rules(),
            'Every history filter must remain safe for mass assignment.',
        );
    }

    public function testSearchConfiguresPaginationAndEverySortableField(): void
    {
        $this->mockWebApplication();

        $provider = (new DebugSearch())->search([], []);
        $pagination = $provider->getPagination();
        $sort = $provider->getSort();

        self::assertInstanceOf(
            Pagination::class,
            $pagination,
            'The search provider must configure a pagination object.',
        );
        self::assertSame(
            50,
            $pagination->getPageSize(),
            'The search provider must configure the correct page size.',
        );
        self::assertInstanceOf(
            Sort::class,
            $sort,
            'The search provider must configure a sort object.',
        );
        self::assertSame(
            [
                'method',
                'ip',
                'tag',
                'time',
                'statusCode',
                'sqlCount',
                'mailCount',
                'processingTime',
                'peakMemory',
            ],
            array_keys($sort->attributes),
            'The search provider must configure every sortable field.',
        );
    }

    public function testSearchAppliesPartialMatchOnTag(): void
    {
        $this->mockWebApplication();

        $records = [
            self::summary(['tag' => 'request-alpha-1']),
            self::summary(['tag' => 'request-alpha-2']),
            self::summary(['tag' => 'request-beta']),
        ];

        self::assertSame(
            2,
            (new DebugSearch())->search(['Debug' => ['tag' => 'alpha']], $records)->getTotalCount(),
            "Partial match on 'alpha' must surface only the two 'request-alpha' entries.",
        );
    }

    public function testSearchAppliesExactMatchOnMethod(): void
    {
        $this->mockWebApplication();

        $records = [
            self::summary(['method' => 'GET']),
            self::summary(['method' => 'GETTING']),
            self::summary(['method' => 'POST']),
        ];

        self::assertSame(
            1,
            (new DebugSearch())->search(['Debug' => ['method' => 'GET']], $records)->getTotalCount(),
            "Exact match on 'GET' must surface only the 'GET' entry.",
        );
    }

    public function testSearchAppliesExactMatchOnAjaxFlag(): void
    {
        $this->mockWebApplication();

        $records = [
            self::summary(['ajax' => true]),
            self::summary(['ajax' => false]),
        ];

        self::assertSame(
            1,
            (new DebugSearch())->search(['Debug' => ['ajax' => '1']], $records)->getTotalCount(),
            "Exact match on '1' must surface only the 'true' entry.",
        );
    }

    public function testSearchAppliesExactMatchOnStatusCode(): void
    {
        $this->mockWebApplication();

        $records = [
            self::summary(['statusCode' => 404]),
            self::summary(['statusCode' => 200]),
        ];

        self::assertSame(
            1,
            (new DebugSearch())->search(['Debug' => ['statusCode' => '404']], $records)->getTotalCount(),
            "Exact match on '404' must surface only the '404' entry.",
        );
    }

    public function testSearchAppliesGreaterThanOperatorOnSqlCount(): void
    {
        $this->mockWebApplication();

        $records = [
            self::summary(['method' => 'GET', 'sqlCount' => 2, 'mailCount' => 0]),
            self::summary(['method' => 'GET', 'sqlCount' => 10, 'mailCount' => 0]),
            self::summary(['method' => 'POST', 'sqlCount' => 20, 'mailCount' => 0]),
        ];

        $search = new DebugSearch();

        $provider = $search->search(['Debug' => ['sqlCount' => '>5']], $records);

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
            self::summary(['method' => 'GET', 'sqlCount' => 1, 'mailCount' => 1]),
            self::summary(['method' => 'GET', 'sqlCount' => 1, 'mailCount' => 5]),
            self::summary(['method' => 'POST', 'sqlCount' => 1, 'mailCount' => 10]),
        ];

        $search = new DebugSearch();

        $provider = $search->search(['Debug' => ['mailCount' => '<5']], $records);

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
            self::summary(['method' => 'GET', 'ip' => '127.0.0.1', 'sqlCount' => 0, 'mailCount' => 0]),
            self::summary(['method' => 'GET', 'ip' => '10.0.0.1', 'sqlCount' => 0, 'mailCount' => 0]),
            self::summary(['method' => 'GET', 'ip' => '192.168.1.1', 'sqlCount' => 0, 'mailCount' => 0]),
        ];

        $search = new DebugSearch();

        $provider = $search->search(['Debug' => ['ip' => '10.']], $records);

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
            self::summary(['url' => '/report >5 ms', 'sqlCount' => 0, 'mailCount' => 0]),
            self::summary(['url' => '/report/10', 'sqlCount' => 0, 'mailCount' => 0]),
        ];

        $provider = (new DebugSearch())->search(['Debug' => ['url' => 'report >5']], $records);

        self::assertSame(
            1,
            $provider->getTotalCount(),
            'Partial text fields must treat embedded operators as text.',
        );
    }

    public function testSearchReturnsAllRowsWhenValidateShortCircuits(): void
    {
        $this->mockWebApplication();

        $records = [
            self::summary(['method' => 'GET', 'sqlCount' => 1, 'mailCount' => 0]),
            self::summary(['method' => 'POST', 'sqlCount' => 2, 'mailCount' => 0]),
        ];

        $search = new class extends DebugSearch {
            public function beforeValidate(): bool
            {
                return false;
            }
        };

        $provider = $search->search(['Debug' => ['method' => 'GET']], $records);

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
            self::summary(['method' => 'GET', 'sqlCount' => 1, 'mailCount' => 0]),
            self::summary(['method' => 'POST', 'sqlCount' => 2, 'mailCount' => 0]),
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
            self::summary(['sqlCount' => 0, 'mailCount' => 0]),
            self::summary(['sqlCount' => 1, 'mailCount' => 0]),
        ];

        $provider = (new DebugSearch())->search(['Debug' => ['sqlCount' => '>0']], $records);

        self::assertSame(
            1,
            $provider->getTotalCount(),
            "'>0' must retain only positive values.",
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private static function summary(array $overrides = []): RequestSummary
    {
        return RequestSummary::fromArray(
            [
                'tag' => 'tag-1',
                'url' => 'https://example.test/',
                'ajax' => false,
                'method' => 'GET',
                'ip' => '127.0.0.1',
                'time' => 1_700_000_000.0,
                'statusCode' => 200,
                'sqlCount' => 0,
                'excessiveCallersCount' => 0,
                'mailCount' => 0,
                'mailFiles' => [],
                'processingTime' => null,
                'peakMemory' => null,
                ...$overrides,
            ],
        );
    }
}
