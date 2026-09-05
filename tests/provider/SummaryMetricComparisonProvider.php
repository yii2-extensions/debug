<?php

declare(strict_types=1);

namespace yii\debug\tests\provider;

use PHPForge\Debug\Storage\RequestSummary;

use function array_replace;

/**
 * Regression cases recorded from the adapters before sharing summary metric calculations.
 */
final class SummaryMetricComparisonProvider
{
    /**
     * @return iterable<string, array{RequestSummary, RequestSummary, list<array{string, string, string, string, string, string|null}>}>
     */
    public static function summaries(): iterable
    {
        yield 'equal absent metrics' => [
            self::summary([]),
            self::summary([]),
            [
                [
                    'Status',
                    'Not captured',
                    'Not captured',
                    'No change',
                    'neutral',
                    null,
                ],
                [
                    'Method',
                    '',
                    '',
                    'No change',
                    'neutral',
                    null,
                ],
                [
                    'AJAX',
                    'No',
                    'No',
                    'No change',
                    'neutral',
                    null,
                ],
                [
                    'Duration',
                    'Not captured',
                    'Not captured',
                    'No change',
                    'neutral',
                    'profiling',
                ],
                [
                    'Peak memory',
                    'Not captured',
                    'Not captured',
                    'No change',
                    'neutral',
                    'profiling',
                ],
                [
                    'SQL queries',
                    '0',
                    '0',
                    'No change',
                    'neutral',
                    'db',
                ],
                [
                    'Mail messages',
                    '0',
                    '0',
                    'No change',
                    'neutral',
                    'mail',
                ],
                [
                    'Excessive DB callers',
                    '0',
                    '0',
                    'No change',
                    'neutral',
                    'db',
                ],
            ],
        ];
        yield 'all metrics increase' => [
            self::summary(
                [
                    'statusCode' => 200,
                    'method' => 'GET',
                    'ajax' => false,
                    'processingTime' => 0.01,
                    'peakMemory' => 1048576,
                    'sqlCount' => 2,
                    'mailCount' => 4,
                    'excessiveCallersCount' => 6,
                ],
            ),
            self::summary(
                [
                    'statusCode' => 500,
                    'method' => 'POST',
                    'ajax' => true,
                    'processingTime' => 0.015,
                    'peakMemory' => 2097152,
                    'sqlCount' => 5,
                    'mailCount' => 7,
                    'excessiveCallersCount' => 10,
                ]),
            [
                [
                    'Status',
                    '200',
                    '500',
                    'Changed',
                    'neutral',
                    null,
                ],
                [
                    'Method',
                    'GET',
                    'POST',
                    'Changed',
                    'neutral',
                    null,
                ],
                [
                    'AJAX',
                    'No',
                    'Yes',
                    'Changed',
                    'neutral',
                    null,
                ],
                [
                    'Duration',
                    '10.00 ms',
                    '15.00 ms',
                    '+5.00 ms (+50.0%)',
                    'up',
                    'profiling',
                ],
                [
                    'Peak memory',
                    '1.00 MB',
                    '2.00 MB',
                    '+1.00 MB (+100.0%)',
                    'up',
                    'profiling',
                ],
                [
                    'SQL queries',
                    '2',
                    '5',
                    '+3 (+150.0%)',
                    'up',
                    'db',
                ],
                [
                    'Mail messages',
                    '4',
                    '7',
                    '+3 (+75.0%)',
                    'up',
                    'mail',
                ],
                [
                    'Excessive DB callers',
                    '6',
                    '10',
                    '+4 (+66.7%)',
                    'up',
                    'db',
                ],
            ],
        ];
        yield 'zero becomes captured' => [
            self::summary([]),
            self::summary(
                [
                    'processingTime' => 0.0,
                    'peakMemory' => 0,
                    'method' => '0',
                ],
            ),
            [
                [
                    'Status',
                    'Not captured',
                    'Not captured',
                    'No change',
                    'neutral',
                    null,
                ],
                [
                    'Method',
                    '',
                    '0',
                    'Changed',
                    'neutral',
                    null,
                ],
                [
                    'AJAX',
                    'No',
                    'No',
                    'No change',
                    'neutral',
                    null,
                ],
                [
                    'Duration',
                    'Not captured',
                    '0.00 ms',
                    'Not comparable',
                    'neutral',
                    'profiling',
                ],
                [
                    'Peak memory',
                    'Not captured',
                    '0.00 MB',
                    'Not comparable',
                    'neutral',
                    'profiling',
                ],
                [
                    'SQL queries',
                    '0',
                    '0',
                    'No change',
                    'neutral',
                    'db',
                ],
                [
                    'Mail messages',
                    '0',
                    '0',
                    'No change',
                    'neutral',
                    'mail',
                ],
                [
                    'Excessive DB callers',
                    '0',
                    '0',
                    'No change',
                    'neutral',
                    'db',
                ],
            ],
        ];
        yield 'rounding boundary' => [
            self::summary(
                [
                    'processingTime' => 0.001004999999,
                    'peakMemory' => 5242,
                ],
            ),
            self::summary(
                [
                    'processingTime' => 0.001005,
                    'peakMemory' => 5243,
                ],
            ),
            [
                [
                    'Status',
                    'Not captured',
                    'Not captured',
                    'No change',
                    'neutral',
                    null,
                ],
                [
                    'Method',
                    '',
                    '',
                    'No change',
                    'neutral',
                    null,
                ],
                [
                    'AJAX',
                    'No',
                    'No',
                    'No change',
                    'neutral',
                    null,
                ],
                [
                    'Duration',
                    '1.00 ms',
                    '1.01 ms',
                    '+0.00 ms (+0.0%)',
                    'up',
                    'profiling',
                ],
                [
                    'Peak memory',
                    '0.00 MB',
                    '0.01 MB',
                    '+0.00 MB (+0.0%)',
                    'up',
                    'profiling',
                ],
                [
                    'SQL queries',
                    '0',
                    '0',
                    'No change',
                    'neutral',
                    'db',
                ],
                [
                    'Mail messages',
                    '0',
                    '0',
                    'No change',
                    'neutral',
                    'mail',
                ],
                [
                    'Excessive DB callers',
                    '0',
                    '0',
                    'No change',
                    'neutral',
                    'db',
                ],
            ],
        ];
        yield 'all metrics decrease' => [
            self::summary(
                [
                    'statusCode' => 500,
                    'method' => 'POST',
                    'ajax' => true,
                    'processingTime' => 0.015,
                    'peakMemory' => 2097152,
                    'sqlCount' => 5,
                    'mailCount' => 7,
                    'excessiveCallersCount' => 10,
                ],
            ),
            self::summary(
                [
                    'statusCode' => 200,
                    'method' => 'GET',
                    'ajax' => false,
                    'processingTime' => 0.01,
                    'peakMemory' => 1048576,
                    'sqlCount' => 2,
                    'mailCount' => 4,
                    'excessiveCallersCount' => 6,
                ],
            ),
            [
                [
                    'Status',
                    '500', '200',
                    'Changed',
                    'neutral',
                    null,
                ],
                [
                    'Method',
                    'POST',
                    'GET',
                    'Changed',
                    'neutral',
                    null,
                ],
                [
                    'AJAX',
                    'Yes',
                    'No',
                    'Changed',
                    'neutral',
                    null,
                ],
                [
                    'Duration',
                    '15.00 ms',
                    '10.00 ms',
                    '-5.00 ms (-33.3%)',
                    'down',
                    'profiling',
                ],
                [
                    'Peak memory',
                    '2.00 MB',
                    '1.00 MB',
                    '-1.00 MB (-50.0%)',
                    'down', 'profiling',
                ],
                [
                    'SQL queries',
                    '5',
                    '2',
                    '-3 (-60.0%)',
                    'down',
                    'db',
                ],
                [
                    'Mail messages',
                    '7',
                    '4',
                    '-3 (-42.9%)',
                    'down',
                    'mail',
                ],
                [
                    'Excessive DB callers',
                    '10',
                    '6',
                    '-4 (-40.0%)',
                    'down',
                    'db',
                ],
            ],
        ];
        yield 'captured zero becomes absent' => [
            self::summary(
                [
                    'processingTime' => 0.0,
                    'peakMemory' => 0,
                    'method' => '0',
                ],
            ),
            self::summary([]),
            [
                [
                    'Status',
                    'Not captured',
                    'Not captured',
                    'No change',
                    'neutral',
                    null,
                ],
                [
                    'Method',
                    '0',
                    '',
                    'Changed',
                    'neutral',
                    null,
                ],
                [
                    'AJAX',
                    'No',
                    'No',
                    'No change',
                    'neutral',
                    null,
                ],
                [
                    'Duration',
                    '0.00 ms',
                    'Not captured',
                    'Not comparable',
                    'neutral',
                    'profiling',
                ],
                [
                    'Peak memory',
                    '0.00 MB',
                    'Not captured',
                    'Not comparable',
                    'neutral',
                    'profiling',
                ],
                [
                    'SQL queries',
                    '0',
                    '0',
                    'No change',
                    'neutral',
                    'db',
                ],
                [
                    'Mail messages',
                    '0',
                    '0',
                    'No change',
                    'neutral',
                    'mail',
                ],
                [
                    'Excessive DB callers',
                    '0',
                    '0',
                    'No change',
                    'neutral',
                    'db',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function summary(array $values): RequestSummary
    {
        return RequestSummary::fromArray(array_replace(RequestSummary::create('sample')->jsonSerialize(), $values));
    }
}
