<?php

declare(strict_types=1);

namespace yii\debug\panels\queue;

use function array_key_exists;
use function count;
use function explode;
use function in_array;
use function strtolower;
use function ucfirst;

/**
 * Resolves the friendly display name of a queue driver from the FQCN of the sender that emitted the event.
 *
 * The yii2-queue ecosystem groups driver implementations under a per-driver namespace segment
 * (`yii\queue\sync\Queue`, `yii\queue\amqp\Queue`, ...). This detector walks the FQCN segments to find a known
 * driver token and falls back to a title-cased namespace fragment when an unknown driver is encountered, so custom
 * drivers still get a readable label without requiring code changes here.
 *
 * Usage example:
 * ```php
 * [$name, $isAsync] = QueueDriverDetector::detect('yii\\queue\\amqp\\Queue');
 * // -> ['AMQP', true]
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class QueueDriverDetector
{
    /**
     * Maps the per-driver namespace token (lowercase) to a display name. Drivers not listed here fall back to a
     * title-cased version of the token, so adding a brand-new driver does not require updating this map.
     *
     * @var array<string, string>
     */
    private const array DRIVER_LABELS = [
        'sync' => 'Sync',
        'db' => 'Database',
        'redis' => 'Redis',
        'amqp' => 'AMQP',
        'amqp_interop' => 'AMQP',
        'beanstalk' => 'Beanstalk',
        'gearman' => 'Gearman',
        'file' => 'File',
        'sqs' => 'SQS',
    ];
    /**
     * Driver tokens whose jobs run in-process during the same request. Everything else is considered async (the worker
     * runs in a separate process) and the panel shows the "exec events live in CLI snapshots" hint.
     *
     * @var list<string>
     */
    private const array SYNC_DRIVERS = ['sync'];

    /**
     * Per-FQCN cache. Detection only depends on the FQCN, so the same queue component hitting the event listener
     * thousands of times during a worker loop pays the lookup cost once.
     *
     * @var array<string, array{0: string, 1: bool}>
     */
    private static array $cache = [];

    /**
     * Detects the friendly driver label and the async flag from a queue class FQCN.
     *
     * @return array{0: string, 1: bool} `[displayName, isAsync]`
     */
    public static function detect(string $fqcn): array
    {
        if (isset(self::$cache[$fqcn])) {
            return self::$cache[$fqcn];
        }

        if ($fqcn === '') {
            return self::$cache[$fqcn] = ['Unknown', true];
        }

        $token = self::extractDriverToken($fqcn);

        $name = array_key_exists($token, self::DRIVER_LABELS)
            ? self::DRIVER_LABELS[$token]
            : self::titleCase($token);

        return self::$cache[$fqcn] = [$name, in_array($token, self::SYNC_DRIVERS, true) === false];
    }

    /**
     * Extracts the per-driver namespace token by walking the FQCN segments and picking the segment immediately before
     * the trailing class name. For `yii\queue\amqp\Queue` this yields `'amqp'`. When the FQCN has a single segment
     * (no namespace) it falls back to the lowercased class name itself.
     */
    private static function extractDriverToken(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        if (count($parts) < 2) {
            return strtolower($fqcn);
        }

        $token = $parts[count($parts) - 2] ?? '';

        return strtolower($token);
    }

    /**
     * Converts a snake_case namespace token into a display label (`'amqp_interop'` → `'AmqpInterop'`).
     */
    private static function titleCase(string $token): string
    {
        if ($token === '') {
            return 'Unknown';
        }

        $parts = explode('_', $token);
        $out = '';

        foreach ($parts as $part) {
            $out .= ucfirst($part);
        }

        return $out;
    }
}
