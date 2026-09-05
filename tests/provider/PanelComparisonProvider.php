<?php

declare(strict_types=1);

namespace yii\debug\tests\provider;

use PHPForge\Debug\Storage\{DebugSnapshot, ExceptionSnapshot, PanelFailure, RequestSummary};

/**
 * Characterization cases for panel selection, capture states, and combined difference counts.
 */
final class PanelComparisonProvider
{
    /**
     * @return iterable<string, array{
     *     DebugSnapshot, DebugSnapshot, array<string, string>,
     *     list<array{string, string, string, string, int, int, int, int}>, bool,
     * }>
     */
    public static function comparisons(): iterable
    {
        $summary = RequestSummary::create('comparison');

        $failure = new PanelFailure(
            PanelFailure::CAPTURE,
            new ExceptionSnapshot(
                'RuntimeException',
                'original diagnostic',
                7,
                '/app/example.php',
                42,
                [],
                'original',
                null,
            ),
        );

        $envelope = ['failure' => $failure->jsonSerialize()];

        yield 'unobserved labels do not create panels' => [
            new DebugSnapshot($summary, [], []),
            new DebugSnapshot($summary, [], []),
            ['unknown' => 'Unknown'],
            [],
            false,
        ];

        $states = [
            'absent' => [[], [], 'Not captured'],
            'empty' => [['p' => []], [], 'Captured'],
            'failure' => [[], ['p' => $failure], 'Failed'],
            'envelope' => [['p' => $envelope], [], 'Captured'],
            'both' => [['p' => ['ignored' => 'baseline payload']], ['p' => $failure], 'Failed'],
        ];
        $counts = [
            'absent' => [
                'absent' => [0, 0, 0, 0], 'empty' => [1, 0, 0, 0], 'failure' => [9, 0, 0, 0],
                'envelope' => [9, 0, 0, 0], 'both' => [9, 0, 0, 0],
            ],
            'empty' => [
                'absent' => [0, 1, 0, 0], 'empty' => [0, 0, 0, 1], 'failure' => [9, 1, 0, 0],
                'envelope' => [9, 1, 0, 0], 'both' => [9, 1, 0, 0],
            ],
            'failure' => [
                'absent' => [0, 9, 0, 0], 'empty' => [1, 9, 0, 0], 'failure' => [0, 0, 0, 9],
                'envelope' => [0, 0, 1, 9], 'both' => [0, 0, 0, 9],
            ],
            'envelope' => [
                'absent' => [0, 9, 0, 0], 'empty' => [1, 9, 0, 0], 'failure' => [0, 0, 1, 9],
                'envelope' => [0, 0, 0, 9], 'both' => [0, 0, 1, 9],
            ],
            'both' => [
                'absent' => [0, 9, 0, 0], 'empty' => [1, 9, 0, 0], 'failure' => [0, 0, 0, 9],
                'envelope' => [0, 0, 1, 9], 'both' => [0, 0, 0, 9],
            ],
        ];

        foreach ($states as $baselineName => [$baselinePanels, $baselineFailures, $baselineState]) {
            foreach ($states as $targetName => [$targetPanels, $targetFailures, $targetState]) {
                [$added, $removed, $changed, $unchanged] = $counts[$baselineName][$targetName];

                yield "{$baselineName} to {$targetName}" => [
                    new DebugSnapshot($summary, $baselinePanels, $baselineFailures),
                    new DebugSnapshot($summary, $targetPanels, $targetFailures),
                    ['p' => 'Panel'],
                    $baselineName === 'absent' && $targetName === 'absent'
                        ? []
                        : [['p', 'Panel', $baselineState, $targetState, $added, $removed, $changed, $unchanged]],
                    $added + $removed + $changed > 0,
                ];
            }
        }

        yield 'configured observed IDs first and extras use regular sorting' => [
            new DebugSnapshot($summary, ['z' => [], 'a10' => [], 'request' => [], 'a2' => []], ['z' => $failure]),
            new DebugSnapshot($summary, ['z' => [], 'a10' => [], 'request' => [], 'a2' => []], ['z' => $failure]),
            ['unknown' => 'Unknown', 'request' => '', 'z' => 'Last configured'],
            [
                ['request', '', 'Captured', 'Captured', 0, 0, 0, 1],
                ['z', 'Last configured', 'Failed', 'Failed', 0, 0, 0, 9],
                ['a10', 'a10', 'Captured', 'Captured', 0, 0, 0, 1],
                ['a2', 'a2', 'Captured', 'Captured', 0, 0, 0, 1],
            ],
            false,
        ];

        yield 'all four maps contribute unique IDs' => [
            new DebugSnapshot($summary, ['d' => []], ['c' => $failure]),
            new DebugSnapshot($summary, ['b' => []], ['a' => $failure]),
            [],
            [
                ['a', 'a', 'Not captured', 'Failed', 9, 0, 0, 0],
                ['b', 'b', 'Not captured', 'Captured', 1, 0, 0, 0],
                ['c', 'c', 'Failed', 'Not captured', 0, 9, 0, 0],
                ['d', 'd', 'Captured', 'Not captured', 0, 1, 0, 0],
            ],
            true,
        ];

        yield 'combined structural counters preserve original diagnostic types' => [
            new DebugSnapshot($summary, ['p' => ['same' => 1, 'changed' => 'secret one', 'removed' => 2]], []),
            new DebugSnapshot($summary, ['p' => ['same' => 1, 'changed' => 'secret two', 'a' => 3, 'b' => 4]], []),
            [],
            [['p', 'p', 'Captured', 'Captured', 2, 1, 1, 1]],
            true,
        ];

        $changedEnvelope = $failure->jsonSerialize();
        $changedEnvelope['extra'] = 'added leaf';

        yield 'state transition with only removed leaves' => [
            new DebugSnapshot($summary, ['p' => ['failure' => $changedEnvelope]], []),
            new DebugSnapshot($summary, [], ['p' => $failure]),
            [],
            [['p', 'p', 'Captured', 'Failed', 0, 1, 0, 9]],
            true,
        ];

        yield 'state transition with only added leaves' => [
            new DebugSnapshot($summary, [], ['p' => $failure]),
            new DebugSnapshot($summary, ['p' => ['failure' => $changedEnvelope]], []),
            [],
            [['p', 'p', 'Failed', 'Captured', 1, 0, 0, 9]],
            true,
        ];

        $changedEnvelope['stage'] = 'hydrate';

        unset($changedEnvelope['exception']);

        yield 'state transition with combined structural differences' => [
            new DebugSnapshot($summary, ['p' => ['failure' => $changedEnvelope]], []),
            new DebugSnapshot($summary, [], ['p' => $failure]),
            [],
            [['p', 'p', 'Captured', 'Failed', 8, 1, 1, 0]],
            true,
        ];

        $renamedEnvelope = $failure->jsonSerialize();

        $renamedEnvelope['stagex'] = PanelFailure::CAPTURE;

        unset($renamedEnvelope['stage']);

        yield 'equal additions and removals do not cancel a state transition' => [
            new DebugSnapshot($summary, ['p' => ['failure' => $renamedEnvelope]], []),
            new DebugSnapshot($summary, [], ['p' => $failure]),
            [],
            [['p', 'p', 'Captured', 'Failed', 1, 1, 0, 8]],
            true,
        ];

        $renamedEnvelope['exception'] = (
            new ExceptionSnapshot(
                'RuntimeException',
                'different message',
                99,
                '/app/example.php',
                42,
                [],
                'original',
                null,
            )
        )->jsonSerialize();

        yield 'changed leaves matching additions and removals are not replaced' => [
            new DebugSnapshot($summary, ['p' => ['failure' => $renamedEnvelope]], []),
            new DebugSnapshot($summary, [], ['p' => $failure]),
            [],
            [['p', 'p', 'Captured', 'Failed', 1, 1, 2, 6]],
            true,
        ];

        $changedFailure = new PanelFailure(
            PanelFailure::HYDRATE,
            new ExceptionSnapshot(
                'RuntimeException',
                'different diagnostic',
                7,
                '/app/example.php',
                42,
                [],
                'original',
                null,
            ),
        );

        yield 'failure changes are not hidden by identical coexisting payloads' => [
            new DebugSnapshot($summary, ['p' => []], ['p' => $failure]),
            new DebugSnapshot($summary, ['p' => []], ['p' => $changedFailure]),
            [],
            [['p', 'p', 'Failed', 'Failed', 0, 0, 2, 7]],
            true,
        ];
    }
}
