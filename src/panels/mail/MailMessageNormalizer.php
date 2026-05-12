<?php

declare(strict_types=1);

namespace yii\debug\panels\mail;

use DateTimeInterface;
use yii\debug\helpers\Coerce;
use yii\debug\helpers\RowField;

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function is_array;
use function is_int;
use function is_string;
use function strtotime;

/**
 * Narrows the loose `mixed` model GridView/ListView passes to the mail item view into a typed {@see MailMessage}.
 *
 * Centralises the address splitting (`to`, `cc`, `bcc`, `reply`), the scalar-to-string coercion for header/body fields
 * and the parsing of the `time` field (accepts {@see DateTimeInterface}, Unix-epoch ints and string representations
 * parseable by `strtotime()`) so the renderer can treat every property as typed.
 *
 * Usage example:
 * ```php
 * echo MailCardRenderer::renderItem(MailMessageNormalizer::from($model));
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class MailMessageNormalizer
{
    /**
     * Builds a {@see MailMessage} from an arbitrary value, falling back to defensible defaults for any field that is
     * missing or has the wrong type.
     */
    public static function from(mixed $data): MailMessage
    {
        $row = is_array($data) ? $data : [];

        return new MailMessage(
            from: self::scalarOrEmpty($row, 'from'),
            to: self::splitAddresses(self::scalarOrEmpty($row, 'to')),
            cc: self::splitAddresses(self::scalarOrEmpty($row, 'cc')),
            bcc: self::splitAddresses(self::scalarOrEmpty($row, 'bcc')),
            replyTo: self::splitAddresses(self::scalarOrEmpty($row, 'reply')),
            subject: self::scalarOrEmpty($row, 'subject'),
            body: self::scalarOrEmpty($row, 'body'),
            headers: self::scalarOrEmpty($row, 'headers'),
            charset: self::scalarOrEmpty($row, 'charset'),
            file: RowField::stringField($row, 'file'),
            isSuccessful: ($row['isSuccessful'] ?? false) === true,
            time: self::timeField($row['time'] ?? null),
        );
    }

    /**
     * Reads `$row[$key]` as a string when it is scalar or {@see \Stringable}, falling back to `''` otherwise.
     *
     * Mail headers and bodies come from third-party mailer libraries that frequently expose `Stringable` payloads
     * (e.g. SwiftMime / Symfony Mailer address objects), so a plain `is_string` narrow would drop legitimate values.
     *
     * @param array<array-key, mixed> $row
     */
    private static function scalarOrEmpty(array $row, string $key): string
    {
        return Coerce::stringOrNull($row[$key] ?? null) ?? '';
    }

    /**
     * Splits a comma-separated address list into a `list<string>` with empty entries dropped and surrounding
     * whitespace trimmed.
     *
     * @return list<string>
     */
    private static function splitAddresses(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $parts = array_map('trim', explode(',', $raw));

        return array_values(array_filter($parts, static fn(string $address): bool => $address !== ''));
    }

    /**
     * Converts the captured time field into a Unix-epoch int. Accepts {@see DateTimeInterface}, ints, and
     * `strtotime()`-parseable strings; everything else collapses to `null`.
     */
    private static function timeField(mixed $value): int|null
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
}
