<?php

declare(strict_types=1);

namespace yii\debug\tests\mail;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\models\search\MailSearch;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see MailSearch} covering attribute labels, validation rules, and the substring-match dispatch
 * backing the Mail panel grid.
 */
#[Group('mail')]
#[Group('search')]
final class MailSearchTest extends TestCase
{
    public function testAttributeLabelsCoverEveryHeaderField(): void
    {
        $labels = (new MailSearch())->attributeLabels();

        self::assertArrayHasKey(
            'from',
            $labels,
            "'from' label must be defined.",
        );
        self::assertArrayHasKey(
            'to',
            $labels,
            "'to' label must be defined.",
        );
        self::assertArrayHasKey(
            'reply',
            $labels,
            "'reply' label must be defined.",
        );
        self::assertArrayHasKey(
            'cc',
            $labels,
            "'cc' label must be defined.",
        );
        self::assertArrayHasKey(
            'bcc',
            $labels,
            "'bcc' label must be defined.",
        );
        self::assertArrayHasKey(
            'subject',
            $labels,
            "'subject' label must be defined.",
        );
        self::assertArrayHasKey(
            'charset',
            $labels,
            "'charset' label must be defined.",
        );
    }

    public function testRulesMarkEveryFilterAsSafe(): void
    {
        $firstRule = (new MailSearch())->rules()[0] ?? null;

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

    public function testSearchAppliesPartialMatchOnSubject(): void
    {
        $this->mockWebApplication();

        $records = [
            [
                'from' => 'a@x.test',
                'to' => 'b@x.test',
                'reply' => '',
                'cc' => '',
                'bcc' => '',
                'subject' => 'Welcome',
                'body' => '',
                'charset' => 'utf-8',
            ],
            [
                'from' => 'a@x.test',
                'to' => 'c@x.test',
                'reply' => '',
                'cc' => '',
                'bcc' => '',
                'subject' => 'Reset password',
                'body' => '',
                'charset' => 'utf-8',
            ],
            [
                'from' => 'a@x.test',
                'to' => 'd@x.test',
                'reply' => '',
                'cc' => '',
                'bcc' => '',
                'subject' => 'Order shipped',
                'body' => '',
                'charset' => 'utf-8',
            ],
        ];

        $search = new MailSearch();

        $provider = $search->search(['MailSearch' => ['subject' => 'Order']], $records);

        self::assertSame(
            1,
            $provider->getTotalCount(),
            "Substring match on 'Order' must surface only the matching message.",
        );
    }

    public function testSearchReturnsAllRecordsWhenFilterEmpty(): void
    {
        $this->mockWebApplication();

        $records = [
            [
                'from' => 'a',
                'to' => 'b',
                'reply' => '',
                'cc' => '',
                'bcc' => '',
                'subject' => 's1',
                'body' => '',
                'charset' => 'utf-8',
            ],
            [
                'from' => 'a',
                'to' => 'c',
                'reply' => '',
                'cc' => '',
                'bcc' => '',
                'subject' => 's2',
                'body' => '',
                'charset' => 'utf-8',
            ],
        ];

        self::assertSame(
            2,
            (new MailSearch())->search([], $records)->getTotalCount(),
            'No filter must keep the full record set.',
        );
    }

    public function testSearchReturnsUnfilteredProviderWhenValidateShortCircuits(): void
    {
        $this->mockWebApplication();

        $records = [
            [
                'from' => 'a',
                'to' => 'b',
                'reply' => '',
                'cc' => '',
                'bcc' => '',
                'subject' => 'x',
                'body' => '',
                'charset' => 'utf-8',
            ],
            [
                'from' => 'a',
                'to' => 'c',
                'reply' => '',
                'cc' => '',
                'bcc' => '',
                'subject' => 'y',
                'body' => '',
                'charset' => 'utf-8',
            ],
        ];

        $search = new class extends MailSearch {
            public function beforeValidate(): bool
            {
                return false;
            }

            public function formName(): string
            {
                return 'MailSearch';
            }
        };

        self::assertSame(
            2,
            $search->search(['MailSearch' => ['subject' => 'x']], $records)->getTotalCount(),
            'Failed validation must short-circuit filtering.',
        );
    }
}
