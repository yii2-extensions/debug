<?php

declare(strict_types=1);

namespace yii\debug\tests\event;

use PHPForge\Debug\Panel\Event\EventRow;
use PHPUnit\Framework\Attributes\Group;
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
        $rules = (new EventSearch())->rules();

        self::assertNotEmpty($rules, 'Rules must be declared.');
    }

    public function testSearchAppliesPartialMatchOnClass(): void
    {
        $this->mockWebApplication();

        $records = [
            self::row(name: 'beforeAction', class: 'yii\\web\\Application', senderClass: 'app\\Foo', isStatic: '0'),
            self::row(name: 'afterAction', class: 'yii\\base\\Module', senderClass: 'app\\Bar', isStatic: '0'),
        ];

        $search = new EventSearch();

        $provider = $search->search(['EventSearch' => ['class' => 'web']], $records);

        self::assertSame(
            1,
            $provider->getTotalCount(),
            "Substring match on 'web' must surface only the 'yii\\\\web\\\\Application' entry.",
        );
    }

    public function testSearchReturnsAllRecordsWhenFilterEmpty(): void
    {
        $this->mockWebApplication();

        $records = [
            self::row(name: 'a', class: 'X', senderClass: 'Y', isStatic: '0'),
            self::row(name: 'b', class: 'X', senderClass: 'Y', isStatic: '1'),
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
            self::row(name: 'b', class: 'X', senderClass: 'Y', isStatic: '1'),
        ];

        $search = new class extends EventSearch {
            public function beforeValidate(): bool
            {
                return false;
            }

            public function formName(): string
            {
                return 'EventSearch';
            }
        };

        self::assertSame(
            2,
            $search->search(['EventSearch' => ['class' => 'X']], $records)->getTotalCount(),
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
