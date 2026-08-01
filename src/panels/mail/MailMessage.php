<?php

declare(strict_types=1);

namespace yii\debug\panels\mail;

use DateTimeInterface;
use yii\debug\helpers\Coerce;

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function is_array;
use function is_int;
use function is_string;
use function strtotime;

/**
 * Typed view-model for a single mail message rendered in the Mail panel detail view.
 *
 * Mirrors the captured `BaseMailer::EVENT_AFTER_SEND` payload after every value has been narrowed and the recipient
 * fields split into per-address lists, so the consuming view iterates and reads typed properties without further
 * runtime checks.
 */
final readonly class MailMessage
{
    public function __construct(
        /**
         * Sender address as captured (typically `name@example.com` or `Name <name@example.com>`).
         */
        public string $from,
        /**
         * @var list<string> Primary recipients split out of the comma-separated `to` field, with empty entries dropped.
         */
        public array $to,
        /**
         * @var list<string> Carbon-copy recipients split out of the comma-separated `cc` field.
         */
        public array $cc,
        /**
         * @var list<string> Blind carbon-copy recipients split out of the comma-separated `bcc` field.
         */
        public array $bcc,
        /**
         * @var list<string> Reply-to addresses split out of the comma-separated `reply` field.
         */
        public array $replyTo,
        /**
         * Subject line as captured.
         */
        public string $subject,
        /**
         * Plain-text body as captured, or `''` when the message had no body.
         */
        public string $body,
        /**
         * Raw RFC-5322 headers as captured by the mailer, joined with line breaks.
         */
        public string $headers,
        /**
         * Charset declared on the message, or `''` when none was set.
         */
        public string $charset,
        /**
         * Path to the persisted `.eml` file, or `''` when the mailer does not expose one.
         */
        public string $file,
        /**
         * `true` when the mailer reported the message as sent, `false` when it reported a failure.
         */
        public bool $isSuccessful,
        /**
         * Capture timestamp as a Unix-epoch second, or `null` when the original payload had no parseable time.
         */
        public int|null $time,
    ) {}

    /**
     * @param array<array-key, mixed> $models
     */
    public static function failedCount(array $models): int
    {
        $failed = 0;

        foreach ($models as $model) {
            if (!self::fromMixed($model)->isSuccessful) {
                $failed++;
            }
        }

        return $failed;
    }

    /**
     * Builds a typed mail message from a data-provider value.
     */
    public static function fromMixed(mixed $data): self
    {
        $row = is_array($data) ? $data : [];

        return new self(
            from: self::scalar($row, 'from'),
            to: self::splitAddresses(self::scalar($row, 'to')),
            cc: self::splitAddresses(self::scalar($row, 'cc')),
            bcc: self::splitAddresses(self::scalar($row, 'bcc')),
            replyTo: self::splitAddresses(self::scalar($row, 'reply')),
            subject: self::scalar($row, 'subject'),
            body: self::scalar($row, 'body'),
            headers: self::scalar($row, 'headers'),
            charset: self::scalar($row, 'charset'),
            file: Coerce::string($row['file'] ?? null),
            isSuccessful: ($row['isSuccessful'] ?? false) === true,
            time: self::normalizeTime($row['time'] ?? null),
        );
    }

    private static function normalizeTime(mixed $value): int|null
    {
        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $parsed = strtotime($value);

            return $parsed === false ? null : $parsed;
        }

        return null;
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private static function scalar(array $row, string $key): string
    {
        return Coerce::stringOrNull($row[$key] ?? null) ?? '';
    }

    /**
     * @return list<string>
     */
    private static function splitAddresses(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $parts = array_map(trim(...), explode(',', $raw));

        return array_values(array_filter($parts, static fn(string $address): bool => $address !== ''));
    }
}
