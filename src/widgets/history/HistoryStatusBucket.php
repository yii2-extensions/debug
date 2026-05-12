<?php

declare(strict_types=1);

namespace yii\debug\widgets\history;

/**
 * Typed view-model for one status-code bucket shown in the History summary header ('2xx', '3xx', '4xx', '5xx').
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final readonly class HistoryStatusBucket
{
    public function __construct(
        /**
         * Display label ('2xx' / '3xx' / '4xx' / '5xx').
         */
        public string $label,
        /**
         * Number of captured requests in this bucket.
         */
        public int $count,
        /**
         * Representative status code from the bucket — used by the pill link's deep-link filter so clicking lands the
         * GridView on a real example.
         */
        public int $sampleCode,
        /**
         * CSS variant token ('success' / 'info' / 'warn' / 'danger').
         */
        public string $variant,
    ) {}
}
