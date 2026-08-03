<?php

declare(strict_types=1);

namespace yii\debug\panels\mail;

use DateTimeInterface;
use yii\debug\helpers\Coerce;
use yii\debug\storage\{PanelRow, Payload};

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function is_int;
use function is_string;
use function strtotime;

/**
 * Typed view-model for a single mail message rendered in the Mail panel detail view.
 *
 * Narrowed once from the `BaseMailer::EVENT_AFTER_SEND` payload, with the recipient fields split into per-address
 * lists, and persisted in that form.
 */
final readonly class MailMessage implements PanelRow
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
     * @param list<self> $models Captured messages.
     */
    public static function failedCount(array $models): int
    {
        $failed = 0;

        foreach ($models as $model) {
            if ($model->isSuccessful === false) {
                $failed++;
            }
        }

        return $failed;
    }

    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)
            ->shape(
                [
                    'from',
                    'to',
                    'cc',
                    'bcc',
                    'replyTo',
                    'subject',
                    'body',
                    'headers',
                    'charset',
                    'file',
                    'isSuccessful',
                    'time',
                ],
            );

        return new self(
            from: $payload->string('from'),
            to: Coerce::stringList($payload->list('to')),
            cc: Coerce::stringList($payload->list('cc')),
            bcc: Coerce::stringList($payload->list('bcc')),
            replyTo: Coerce::stringList($payload->list('replyTo')),
            subject: $payload->string('subject'),
            body: $payload->string('body'),
            headers: $payload->string('headers'),
            charset: $payload->string('charset'),
            file: $payload->string('file'),
            isSuccessful: $payload->bool('isSuccessful'),
            time: $payload->nullableInt('time'),
        );
    }

    /**
     * Narrows one captured `EVENT_AFTER_SEND` payload into a typed message.
     *
     * @param array<array-key, mixed> $row Captured payload.
     */
    public static function fromCapture(array $row): self
    {
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

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'replyTo' => $this->replyTo,
            'subject' => $this->subject,
            'body' => $this->body,
            'headers' => $this->headers,
            'charset' => $this->charset,
            'file' => $this->file,
            'isSuccessful' => $this->isSuccessful,
            'time' => $this->time,
        ];
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
        $parts = array_map(trim(...), explode(',', $raw));

        return array_values(array_filter($parts, static fn(string $address): bool => $address !== ''));
    }
}
