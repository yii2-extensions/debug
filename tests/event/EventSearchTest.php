<?php

declare(strict_types=1);

namespace yii\debug\tests\event;

use PHPForge\Debug\Panel\Event\EventRow;
use PHPUnit\Framework\Attributes\Group;
use yii\data\{Pagination, Sort};
use yii\debug\models\search\EventSearch;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see EventSearch} covering attribute labels, validation rules, and the substring/exact-match dispatch
 * backing the Event panel grid.
 */
#[Group('event')]
#[Group('search')]
final class EventSearchTest extends TestCase
{
    public function testAttributeLabelsCoverEveryFilterField(): void
    {
        $labels = (new EventSearch())->attributeLabels();

        self::assertArrayHasKey(
            'name',
            $labels,
            "'name' label must be defined.",
        );
        self::assertArrayHasKey(
            'class',
            $labels,
            "'class' label must be defined.",
        );
        self::assertArrayHasKey(
            'senderClass',
            $labels,
            "'senderClass' label must be defined.",
        );
        self::assertArrayHasKey(
            'isStatic',
            $labels,
            "'isStatic' label must be defined.",
        );
    }

    public function testRulesIncludeStringAndBooleanValidators(): void
    {
        $search = new EventSearch();

        self::assertSame(
            [
                [['name', 'class', 'senderClass'], 'string'],
                [['isStatic'], 'boolean'],
                [['class', 'isStatic', 'name', 'senderClass'], 'safe'],
            ],
            $search->rules(),
        );
    }

    public function testSearchAppliesExactMatchOnStaticFlag(): void
    {
        $this->mockWebApplication();

        $records = [
            self::row(isStatic: '1'),
            self::row(name: 'other', isStatic: '0'),
        ];

        self::assertSame(
            1,
            (new EventSearch())->search(['Event' => ['isStatic' => '1']], $records)->getTotalCount(),
            'Exact match on static flag must surface only the static event.',
        );
    }

    public function testSearchAppliesPartialMatchOnClass(): void
    {
        $this->mockWebApplication();

        $records = [
            self::row(name: 'beforeAction', class: 'yii\\web\\Application', senderClass: 'app\\Foo', isStatic: '0'),
            self::row(name: 'afterAction', class: 'yii\\base\\Module', senderClass: 'app\\Bar', isStatic: '0'),
        ];

        $search = new EventSearch();

        $provider = $search->search(['Event' => ['class' => 'web']], $records);

        self::assertSame(
            1,
            $provider->getTotalCount(),
            "Substring match on 'web' must surface only the 'yii\\\\web\\\\Application' entry.",
        );
    }

    public function testSearchAppliesPartialMatchOnName(): void
    {
        $this->mockWebApplication();

        $records = [
            self::row(name: 'beforeAction'),
            self::row(name: 'afterAction'),
            self::row(name: 'init'),
        ];

        self::assertSame(
            2,
            (new EventSearch())->search(['Event' => ['name' => 'Action']], $records)->getTotalCount(),
            'Substring match on name must surface only the before/afterAction events.',
        );
    }

    public function testSearchAppliesPartialMatchOnSenderClass(): void
    {
        $this->mockWebApplication();

        $records = [
            self::row(senderClass: 'app\\controllers\\SiteController'),
            self::row(name: 'other', senderClass: 'app\\services\\Mailer'),
            self::row(name: 'last', senderClass: 'console\\Worker'),
        ];

        self::assertSame(
            2,
            (new EventSearch())->search(['Event' => ['senderClass' => 'app\\']], $records)->getTotalCount(),
            'Substring match on senderClass must surface only the SiteController and Mailer events.',
        );
    }

    public function testSearchConfiguresEveryDisplayedEventFieldForSorting(): void
    {
        $this->mockWebApplication();

        $provider = (new EventSearch())->search([], []);

        $pagination = $provider->getPagination();
        $sort = $provider->getSort();

        self::assertInstanceOf(
            Pagination::class,
            $pagination,
            'Event search results must be paginated.',
        );
        self::assertSame(
            50,
            $pagination->getPageSize(),
            'Event search results must be paginated with a default page size of 50.',
        );
        self::assertInstanceOf(
            Sort::class,
            $sort,
            'Event sorting must be enabled.',
        );
        self::assertSame(
            ['time', 'name', 'class', 'senderClass', 'isStatic'],
            array_keys($sort->attributes),
            'Every displayed event field must be sortable.',
        );
        self::assertSame(
            ['time' => SORT_ASC],
            $sort->defaultOrder,
            'Default sort order must be ascending by time.',
        );
    }

    public function testSearchReturnsAllRecordsWhenFilterEmpty(): void
    {
        $this->mockWebApplication();

        $records = [
            self::row(name: 'a', class: 'X', senderClass: 'Y', isStatic: '0'),
            self::row(name: 'b', class: 'Z', senderClass: 'Y', isStatic: '1'),
        ];

        $search = new EventSearch();

        self::assertSame(
            2,
            $search->search([], $records)->getTotalCount(),
            'No filter must keep the full record set.',
        );
    }

    public function testSearchReturnsUnfilteredProviderWhenValidateShortCircuits(): void
    {
        $this->mockWebApplication();

        $records = [
            self::row(name: 'a', class: 'X', senderClass: 'Y', isStatic: '0'),
            self::row(name: 'b', class: 'Z', senderClass: 'Y', isStatic: '1'),
        ];

        $search = new class extends EventSearch {
            public function beforeValidate(): bool
            {
                return false;
            }
        };

        self::assertSame(
            2,
            $search->search(['Event' => ['class' => 'X']], $records)->getTotalCount(),
            'Failed validation must short-circuit filtering.',
        );
    }

    private static function row(
        string $name = 'init',
        string $class = 'yii\\base\\Event',
        string $senderClass = 'App',
        string $isStatic = '0',
    ): EventRow {
        return new EventRow(1.0, $name, $class, $isStatic, $senderClass);
    }
}
