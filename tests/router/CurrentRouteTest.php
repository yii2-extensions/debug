<?php

declare(strict_types=1);

namespace yiiunit\debug\router;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use yii\debug\models\router\CurrentRoute;
use yii\log\Logger;

/**
 * Unit tests for {@see CurrentRoute} covering log-message classification (informational vs.
 * rule-match), parent-rule deduplication, and counters/flags surfaced on the panel detail view.
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 2.1.29
 */
#[Group('router')]
final class CurrentRouteTest extends TestCase
{
    public function testCounterReflectsTotalRuleEntries(): void
    {
        $router = new CurrentRoute([
            'messages' => [
                [['rule' => 'test rule 1', 'match' => false], 999],
                [['rule' => 'test rule 2', 'match' => false], 999],
            ],
        ]);

        self::assertSame(
            [
                ['rule' => 'test rule 1', 'match' => false],
                ['rule' => 'test rule 2', 'match' => false],
            ],
            $router->logs,
            'All rule-trace entries must round-trip into logs in input order.',
        );
        self::assertSame(2, $router->count, 'Counter must equal the number of rule-trace entries.');
    }

    public function testEmptyMessagesYieldEmptyDefaults(): void
    {
        $router = new CurrentRoute();

        self::assertSame([], $router->messages, 'No input messages must yield an empty messages array.');
        self::assertSame('', $router->route, 'Route must default to empty string.');
        self::assertSame('', $router->action, 'Action must default to empty string.');
        self::assertNull($router->message, 'No textual message must yield null.');
        self::assertSame([], $router->logs, 'No rule-trace messages must yield an empty logs array.');
        self::assertSame(0, $router->count, 'Counter must start at zero with no messages.');
        self::assertFalse($router->hasMatch, 'No matching rule means hasMatch defaults to false.');
    }

    public function testMatchingRuleEntryFlipsHasMatchAndIncrementsCounter(): void
    {
        $router = new CurrentRoute([
            'messages' => [[['rule' => 'test rule', 'match' => true], 999]],
        ]);

        self::assertSame([['rule' => 'test rule', 'match' => true]], $router->logs, 'Rule-trace entry must round-trip into logs.');
        self::assertSame(1, $router->count, 'A single rule-trace entry must set counter to 1.');
        self::assertTrue($router->hasMatch, 'A matching rule must flip hasMatch to true.');
    }

    public function testNonMatchingRuleEntryStillCountsButLeavesHasMatchFalse(): void
    {
        $router = new CurrentRoute([
            'messages' => [[['rule' => 'test rule', 'match' => false], 999]],
        ]);

        self::assertSame([['rule' => 'test rule', 'match' => false]], $router->logs, 'Non-matching rules must still appear in logs.');
        self::assertSame(1, $router->count, 'Counter must include non-matching attempts.');
        self::assertFalse($router->hasMatch, 'Non-matching attempts must leave hasMatch false.');
    }

    public function testParentRuleEntryIsSkippedAfterChildIsRecorded(): void
    {
        $router = new CurrentRoute([
            'messages' => [
                [['rule' => 'test rule', 'match' => false, 'parent' => 'test parent'], 999],
                [['rule' => 'test parent', 'match' => false], 999],
            ],
        ]);

        self::assertSame(
            [['rule' => 'test rule', 'match' => false, 'parent' => 'test parent']],
            $router->logs,
            'Parent rules must be deduplicated when a child entry already references them.',
        );
        self::assertSame(1, $router->count, 'Deduplicated parent must not be counted twice.');
    }

    public function testTextualMessageIsExposedSeparately(): void
    {
        $router = new CurrentRoute(['messages' => [['test', Logger::LEVEL_TRACE]]]);

        self::assertSame('test', $router->message, 'Plain-text TRACE messages must populate the textual `message` field.');
        self::assertSame([], $router->logs, 'Plain-text messages must NOT enter the rule-trace `logs` array.');
        self::assertSame(0, $router->count, 'Counter must remain zero for non-rule messages.');
        self::assertFalse($router->hasMatch, 'Plain-text messages do not flip hasMatch.');
    }
}
