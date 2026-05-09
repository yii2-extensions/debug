<?php

declare(strict_types=1);

namespace yii\debug\panels\mail;

use DateTimeInterface;
use Stringable;

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function is_array;
use function is_int;
use function is_scalar;
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
            from: self::stringField($row, 'from'),
            to: self::splitAddresses(self::stringField($row, 'to')),
            cc: self::splitAddresses(self::stringField($row, 'cc')),
            bcc: self::splitAddresses(self::stringField($row, 'bcc')),
            replyTo: self::splitAddresses(self::stringField($row, 'reply')),
            subject: self::stringField($row, 'subject'),
            body: self::stringField($row, 'body'),
            headers: self::stringField($row, 'headers'),
            charset: self::stringField($row, 'charset'),
            file: self::fileField($row),
            isSuccessful: ($row['isSuccessful'] ?? false) === true,
            time: self::timeField($row['time'] ?? null),
        );
    }

    /**
     * `file` must be a real string path to be useful — non-strings collapse to empty.
     *
     * @param array<array-key, mixed> $row
     */
    private static function fileField(array $row): string
    {
        $value = $row['file'] ?? null;

        return is_string($value) ? $value : '';
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
     * @param array<array-key, mixed> $row
     */
    private static function stringField(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return '';
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
