<?php

declare(strict_types=1);

namespace yiiunit\debug;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Stringable;
use yii\debug\panels\mail\MailMessageNormalizer;

/**
 * Unit tests for {@see MailMessageNormalizer} covering payload narrowing, address splitting, time parsing and the
 * scalar-to-string coercion of header/body fields.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('panel')]
#[Group('mail')]
final class MailMessageNormalizerTest extends TestCase
{
    public function testFromCoercesScalarHeaderFieldsToStrings(): void
    {
        $message = MailMessageNormalizer::from(
            ['from' => 42, 'subject' => true, 'charset' => 1.5],
        );

        self::assertSame(
            '42',
            $message->from,
            'Int sender must coerce to string.',
        );
        self::assertSame(
            '1',
            $message->subject,
            'Bool subject must coerce to string.',
        );
        self::assertSame(
            '1.5',
            $message->charset,
            'Float charset must coerce to string.',
        );
    }

    public function testFromCoercesStringableHeaderFieldsToStrings(): void
    {
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'rendered';
            }
        };

        $message = MailMessageNormalizer::from(
            ['subject' => $stringable],
        );

        self::assertSame(
            'rendered',
            $message->subject,
            "Stringable subject must coerce via '__toString()'.",
        );
    }

    public function testFromCollapsesNonStringFileToEmpty(): void
    {
        self::assertSame(
            '',
            MailMessageNormalizer::from(['file' => 42])->file,
            "Non-string `file` must collapse to ''.",
        );
    }

    public function testFromCollapsesUnparseableTimeToNull(): void
    {
        self::assertNull(
            MailMessageNormalizer::from(['time' => 'not a date'])->time,
            "Garbage string must collapse to 'null'.",
        );
        self::assertNull(
            MailMessageNormalizer::from(['time' => ''])->time,
            "Empty string must collapse to 'null'.",
        );
        self::assertNull(
            MailMessageNormalizer::from(['time' => null])->time,
            "'null' must collapse to 'null'.",
        );
        self::assertNull(
            MailMessageNormalizer::from(['time' => ['nested']])->time,
            "Array must collapse to 'null'.",
        );
    }

    public function testFromDropsEmptySegmentsBetweenCommas(): void
    {
        $message = MailMessageNormalizer::from(
            ['to' => 'a@example.com,, ,b@example.com,'],
        );

        self::assertSame(
            ['a@example.com', 'b@example.com'],
            $message->to,
            'Empty segments must be dropped.',
        );
    }

    public function testFromFallsBackToEmptyWhenStringFieldsAreNonScalar(): void
    {
        $message = MailMessageNormalizer::from([
            'from' => ['nested'],
            'subject' => null,
        ]);

        self::assertSame('', $message->from, 'Array `from` must collapse to `\'\'`.');
        self::assertSame('', $message->subject, 'Null `subject` must collapse to `\'\'`.');
    }

    public function testFromKeepsIntTimeAsIs(): void
    {
        self::assertSame(
            1_700_000_000,
            MailMessageNormalizer::from(['time' => 1_700_000_000]),
            'Int time must round-trip unchanged.',
        );
    }

    public function testFromMapsTruthyIsSuccessfulOnlyWhenStrictlyTrue(): void
    {
        self::assertTrue(
            MailMessageNormalizer::from(['isSuccessful' => true])->isSuccessful,
            "'true' must round-trip.",
        );
        self::assertFalse(
            MailMessageNormalizer::from(['isSuccessful' => 1])->isSuccessful,
            "'1' must not be accepted (strict comparison)."
        );
        self::assertFalse(
            MailMessageNormalizer::from(['isSuccessful' => 'true'])->isSuccessful,
            "'true' must not be accepted."
        );
        self::assertFalse(
            MailMessageNormalizer::from(['isSuccessful' => false])->isSuccessful,
            "'false' must yield 'false'."
        );
        self::assertFalse(
            MailMessageNormalizer::from([])->isSuccessful,
            "Missing flag must default to 'false'."
        );
    }

    public function testFromParsesDateTimeInterfaceAsUnixTimestamp(): void
    {
        $datetime = new DateTimeImmutable('2024-06-15T12:34:56+00:00');

        $message = MailMessageNormalizer::from(
            ['time' => $datetime],
        );

        self::assertSame(
            $datetime->getTimestamp(),
            $message->time,
            'DateTimeInterface must yield its Unix timestamp.',
        );
    }

    public function testFromParsesStringTimeViaStrtotime(): void
    {
        $message = MailMessageNormalizer::from(
            ['time' => '2024-06-15T12:34:56+00:00'],
        );

        self::assertSame(
            strtotime('2024-06-15T12:34:56+00:00'),
            $message->time,
            'Parseable string must coerce via `strtotime`.',
        );
    }

    public function testFromReturnsAllEmptyDefaultsWhenInputIsNotArray(): void
    {
        $message = MailMessageNormalizer::from(
            'not an array',
        );

        self::assertSame(
            '',
            $message->from,
            "Non-array input must yield empty 'from'.",
        );
        self::assertSame(
            [],
            $message->to,
            "Non-array input must yield empty 'to'.",
        );
        self::assertSame(
            [],
            $message->cc,
            "Non-array input must yield empty 'cc'.",
        );
        self::assertSame(
            [],
            $message->bcc,
            "Non-array input must yield empty 'bcc'.",
        );
        self::assertSame(
            [],
            $message->replyTo,
            "Non-array input must yield empty 'replyTo'.",
        );
        self::assertSame(
            '',
            $message->subject,
            "Non-array input must yield empty 'subject'.",
        );
        self::assertSame(
            '',
            $message->body,
            "Non-array input must yield empty 'body'.",
        );
        self::assertSame(
            '',
            $message->headers,
            "Non-array input must yield empty 'headers'.",
        );
        self::assertSame(
            '',
            $message->charset,
            "Non-array input must yield empty 'charset'.",
        );
        self::assertSame(
            '',
            $message->file,
            "Non-array input must yield empty 'file'.",
        );
        self::assertFalse(
            $message->isSuccessful,
            "Non-array input must yield 'isSuccessful = false'.",
        );
        self::assertNull(
            $message->time,
            "Non-array input must yield 'null' 'time'.",
        );
    }

    public function testFromRoundTripsTypedFields(): void
    {
        $message = MailMessageNormalizer::from(
            [
                'from' => 'sender@example.com',
                'subject' => 'Hello',
                'body' => 'Body content.',
                'headers' => 'X-Foo: bar',
                'charset' => 'UTF-8',
                'file' => '/tmp/mail.eml',
                'isSuccessful' => true,
            ],
        );

        self::assertSame(
            'sender@example.com',
            $message->from,
            'From must round-trip.',
        );
        self::assertSame(
            'Hello',
            $message->subject,
            'Subject must round-trip.',
        );
        self::assertSame(
            'Body content.',
            $message->body,
            'Body must round-trip.',
        );
        self::assertSame(
            'X-Foo: bar',
            $message->headers,
            'Headers must round-trip.',
        );
        self::assertSame(
            'UTF-8',
            $message->charset,
            'Charset must round-trip.',
        );
        self::assertSame(
            '/tmp/mail.eml',
            $message->file,
            'File path must round-trip.',
        );
        self::assertTrue(
            $message->isSuccessful,
            '`isSuccessful = true` must round-trip.',
        );
    }

    public function testFromSplitsCommaSeparatedRecipients(): void
    {
        $message = MailMessageNormalizer::from(
            [
                'to' => 'a@example.com, b@example.com,c@example.com',
                'cc' => 'cc@example.com',
                'bcc' => '',
                'reply' => 'reply1@example.com,reply2@example.com',
            ],
        );

        self::assertSame(
            ['a@example.com', 'b@example.com', 'c@example.com'],
            $message->to,
            'TO must split on commas and trim.',
        );
        self::assertSame(
            ['cc@example.com'],
            $message->cc,
            'Single CC must yield a one-element list.',
        );
        self::assertSame(
            [],
            $message->bcc,
            'Empty BCC string must yield `[]`.',
        );
        self::assertSame(
            ['reply1@example.com', 'reply2@example.com'],
            $message->replyTo,
            'Reply-to must split on commas.',
        );
    }
}
