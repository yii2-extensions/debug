<?php

declare(strict_types=1);

namespace yii\debug\tests\router;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use yii\debug\models\router\CurrentRoute;
use yii\debug\panels\router\RouterSnapshot;
use yii\log\Logger;

/**
 * Unit tests for {@see CurrentRoute} and the {@see RouterSnapshot::capture()} trace replay it exposes, covering
 * log-message classification (informational vs. rule-match), parent-rule deduplication, and the derived counters.
 *
 * @since 0.2
 */
#[Group('router')]
final class CurrentRouteTest extends TestCase
{
    public function testCounterReflectsTotalRuleEntries(): void
    {
        $route = self::route(
            [
                [['rule' => 'test rule 1', 'match' => false], 999],
                [['rule' => 'test rule 2', 'match' => false], 999],
            ],
        );

        self::assertCount(2, $route->logs, 'Every rule-trace entry must round-trip into logs.');
        self::assertSame('test rule 1', $route->logs[0]->rule, 'Input order must be preserved.');
        self::assertSame('test rule 2', $route->logs[1]->rule, 'Input order must be preserved.');
        self::assertSame(2, $route->count, 'Counter must equal the number of rule-trace entries.');
    }

    public function testEmptyMessagesYieldEmptyDefaults(): void
    {
        $route = self::route([]);

        self::assertSame([], $route->logs, 'No trace means no logs.');
        self::assertSame(0, $route->count, 'No trace means a zero counter.');
        self::assertFalse($route->hasMatch, 'No trace means no match.');
        self::assertNull($route->message, 'No trace means no informational message.');
    }

    public function testFromSnapshotYieldsEmptyDefaultsWithoutASnapshot(): void
    {
        $route = CurrentRoute::fromSnapshot(null);

        self::assertSame('', $route->route, 'No snapshot means no resolved route.');
        self::assertSame('', $route->action, 'No snapshot means no dispatched action.');
        self::assertSame([], $route->logs, 'No snapshot means no rule trace.');
    }

    public function testMalformedRulePayloadsAreSkipped(): void
    {
        $route = self::route(
            [
                [['rule' => 'valid', 'match' => false], 999],
                [['rule' => 'missing-match'], 999],
                [['match' => true], 999],
                [['rule' => 42, 'match' => true], 999],
                ['not-a-trace-level-string', 999],
            ],
        );

        self::assertCount(1, $route->logs, 'Only the well-formed rule entry survives.');
        self::assertSame('valid', $route->logs[0]->rule, 'The surviving entry keeps its rule name.');
    }

    public function testMatchingRuleEntryFlipsHasMatchAndIncrementsCounter(): void
    {
        $route = self::route([[['rule' => 'matched', 'match' => true], 999]]);

        self::assertTrue($route->hasMatch, 'A matching rule must raise the flag.');
        self::assertSame(1, $route->count, 'A matching rule still counts.');
    }

    public function testNonMatchingRuleEntryStillCountsButLeavesHasMatchFalse(): void
    {
        $route = self::route([[['rule' => 'missed', 'match' => false], 999]]);

        self::assertFalse($route->hasMatch, 'A non-matching rule must leave the flag down.');
        self::assertSame(1, $route->count, 'A non-matching rule still counts.');
    }

    public function testParentRuleEntryIsSkippedAfterChildIsRecorded(): void
    {
        $route = self::route(
            [
                [['rule' => 'child', 'match' => false, 'parent' => 'parent'], 999],
                [['rule' => 'parent', 'match' => false], 999],
            ],
        );

        self::assertCount(1, $route->logs, 'The parent rule echoed after its child must be dropped.');
        self::assertSame('child', $route->logs[0]->rule, 'The child entry is the one kept.');
        self::assertSame('parent', $route->logs[0]->parent, 'The child entry keeps its parent reference.');
    }

    public function testTextualMessageIsExposedSeparately(): void
    {
        $route = self::route(
            [
                ['Request parsed', Logger::LEVEL_TRACE],
                [['rule' => 'matched', 'match' => true], 999],
            ],
        );

        self::assertSame('Request parsed', $route->message, 'Trace-level strings surface as the message.');
        self::assertCount(1, $route->logs, 'The informational message is not a rule row.');
    }

    /**
     * @param array<int, array<int|string, mixed>> $messages
     */
    private static function route(array $messages): CurrentRoute
    {
        return CurrentRoute::fromSnapshot(RouterSnapshot::capture(null, $messages, 'site/index'));
    }
}
