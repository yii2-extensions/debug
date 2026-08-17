<?php

declare(strict_types=1);

namespace yii\debug\tests\mail;

use PHPForge\Debug\Panel\Mail\MailMessage;
use PHPUnit\Framework\Attributes\Group;
use yii\data\{Pagination, Sort};
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
            'replyTo',
            $labels,
            "'replyTo' label must be defined.",
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
        self::assertSame(
            [[['from', 'to', 'replyTo', 'cc', 'bcc', 'subject', 'body', 'charset'], 'safe']],
            (new MailSearch())->rules(),
            'Every searchable mail field must remain safe for mass assignment.',
        );
    }

    public function testSearchAppliesPartialMatchOnSubject(): void
    {
        $this->mockWebApplication();

        $records = [
            MailMessage::fromCapture(['from' => 'a@x.test', 'to' => 'b@x.test', 'subject' => 'Welcome', 'charset' => 'utf-8']),
            MailMessage::fromCapture(['from' => 'a@x.test', 'to' => 'c@x.test', 'subject' => 'Reset password', 'charset' => 'utf-8']),
            MailMessage::fromCapture(['from' => 'a@x.test', 'to' => 'd@x.test', 'subject' => 'Order shipped', 'charset' => 'utf-8']),
        ];

        $search = new MailSearch();

        $provider = $search->search(['MailSearch' => ['subject' => 'Order']], $records);

        self::assertSame(
            1,
            $provider->getTotalCount(),
            "Substring match on 'Order' must surface only the matching message.",
        );
    }

    public function testSearchAppliesPartialMatchToEveryNonSubjectFilter(): void
    {
        $this->mockWebApplication();

        $matching = MailMessage::fromCapture(
            [
                'from' => 'Sender Alpha <alpha@example.test>',
                'to' => 'to-alpha@example.test',
                'reply' => 'reply-alpha@example.test',
                'cc' => 'cc-alpha@example.test',
                'bcc' => 'bcc-alpha@example.test',
                'subject' => 'Alpha subject',
                'body' => 'Alpha message body',
                'charset' => 'utf-8',
            ],
        );
        $other = MailMessage::fromCapture(
            [
                'from' => 'Sender Beta <beta@example.test>',
                'to' => 'to-beta@example.test',
                'reply' => 'reply-beta@example.test',
                'cc' => 'cc-beta@example.test',
                'bcc' => 'bcc-beta@example.test',
                'subject' => 'Beta subject',
                'body' => 'Beta message body',
                'charset' => 'iso-8859-1',
            ],
        );

        $filters = [
            'from' => 'Alpha',
            'to' => 'to-alpha',
            'replyTo' => 'reply-alpha',
            'cc' => 'cc-alpha',
            'bcc' => 'bcc-alpha',
            'body' => 'Alpha message',
            'charset' => 'utf',
        ];

        foreach ($filters as $attribute => $value) {
            $provider = (new MailSearch())->search(
                ['MailSearch' => [$attribute => $value]],
                [$matching, $other],
            );

            self::assertSame(
                [$matching],
                $provider->allModels,
                "The '{$attribute}' filter must apply a partial match.",
            );
        }
    }

    public function testSearchConfiguresPaginationAndSorting(): void
    {
        $this->mockWebApplication();

        $provider = (new MailSearch())->search([], []);

        $pagination = $provider->getPagination();
        $sort = $provider->getSort();

        self::assertInstanceOf(
            Pagination::class,
            $pagination,
            'Mail results must remain paginated.',
        );
        self::assertSame(
            20,
            $pagination->getPageSize(),
            'Mail results must preserve the configured default page size.',
        );
        self::assertInstanceOf(
            Sort::class,
            $sort,
            'Mail results must remain sortable.',
        );
        self::assertSame(
            ['from', 'to', 'replyTo', 'cc', 'bcc', 'subject', 'body', 'charset'],
            array_keys($sort->attributes),
            'Every displayed mail field must remain sortable.',
        );
    }

    public function testSearchReturnsAllRecordsWhenFilterEmpty(): void
    {
        $this->mockWebApplication();

        $records = [
            MailMessage::fromCapture(['from' => 'a', 'to' => 'b', 'subject' => 's1', 'charset' => 'utf-8']),
            MailMessage::fromCapture(['from' => 'a', 'to' => 'c', 'subject' => 's2', 'charset' => 'utf-8']),
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
            MailMessage::fromCapture(['from' => 'a', 'to' => 'b', 'subject' => 'x', 'charset' => 'utf-8']),
            MailMessage::fromCapture(['from' => 'a', 'to' => 'c', 'subject' => 'y', 'charset' => 'utf-8']),
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
