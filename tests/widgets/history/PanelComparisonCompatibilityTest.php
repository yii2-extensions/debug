<?php

declare(strict_types=1);

namespace yii\debug\tests\widgets\history;

use PHPForge\Debug\Storage\DebugSnapshot;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;
use yii\debug\tests\provider\PanelComparisonProvider;
use yii\debug\widgets\history\HistoryComparison;

/**
 * Locks the original adapter output before and after sharing panel comparison.
 */
#[Group('history')]
final class PanelComparisonCompatibilityTest extends TestCase
{
    /**
     * @param array<string, string> $labels
     * @param list<array{string, string, string, string, int, int, int, int}> $expected
     */
    #[DataProviderExternal(PanelComparisonProvider::class, 'comparisons')]
    public function testFromSnapshotsPreservesPanelContracts(
        DebugSnapshot $baseline,
        DebugSnapshot $target,
        array $labels,
        array $expected,
        bool $hasDifferences,
    ): void {
        $beforeBaseline = $baseline->jsonSerialize();
        $beforeTarget = $target->jsonSerialize();

        $comparison = HistoryComparison::fromSnapshots($baseline, $target, $labels);
        $actual = [];

        foreach ($comparison->panels as $panel) {
            $actual[] = [
                $panel->id,
                $panel->label,
                $panel->baselineState,
                $panel->targetState,
                $panel->added,
                $panel->removed,
                $panel->changed,
                $panel->unchanged,
            ];

            self::assertSame(
                $panel->added + $panel->removed + $panel->changed,
                $panel->differenceCount(),
                'The total must exclude unchanged leaves.',
            );
        }

        self::assertSame(
            $expected,
            $actual,
            'Panel IDs, labels, order, states, and counts must remain exact.',
        );
        self::assertSame(
            $hasDifferences,
            $comparison->hasDifferences(),
            'Difference detection must remain exact.',
        );
        self::assertSame(
            $beforeBaseline,
            $baseline->jsonSerialize(),
            'Baseline diagnostics must remain intact.',
        );
        self::assertSame(
            $beforeTarget,
            $target->jsonSerialize(),
            'Target diagnostics must remain intact.',
        );
    }
}
