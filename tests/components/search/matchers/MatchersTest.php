<?php

declare(strict_types=1);

namespace yii\debug\tests\components\search\matchers;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\components\search\matchers\{GreaterThan, GreaterThanOrEqual, LowerThan};
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see GreaterThan}, {@see GreaterThanOrEqual}, and {@see LowerThan} numeric matchers covering the
 * `>`/`>=`/`<` comparison surfaces consumed by {@see \yii\debug\components\search\Filter::addMatcher()}.
 */
#[Group('matchers')]
#[Group('search')]
final class MatchersTest extends TestCase
{
    public function testGreaterThanMatchesStrictlyAboveBaseValue(): void
    {
        $matcher = new GreaterThan(['value' => 5]);

        self::assertTrue(
            $matcher->match(10),
            "'10' must satisfy '> 5'.",
        );
        self::assertFalse(
            $matcher->match(5),
            "'5' must not satisfy '> 5' (strict).",
        );
        self::assertFalse(
            $matcher->match(1),
            "'1' must not satisfy '> 5'.",
        );
    }

    public function testGreaterThanOrEqualIncludesBaseValueBoundary(): void
    {
        $matcher = new GreaterThanOrEqual(['value' => 5]);

        self::assertTrue(
            $matcher->match(5),
            "'5' must satisfy '>= 5' at the boundary.",
        );
        self::assertTrue(
            $matcher->match(6),
            "'6' must satisfy '>= 5'.",
        );
        self::assertFalse(
            $matcher->match(4),
            "'4' must not satisfy '>= 5'.",
        );
    }

    public function testLowerThanMatchesStrictlyBelowBaseValue(): void
    {
        $matcher = new LowerThan(['value' => 5]);

        self::assertTrue(
            $matcher->match(1),
            "'1' must satisfy '< 5'.",
        );
        self::assertFalse(
            $matcher->match(5),
            "'5' must not satisfy '< 5' (strict).",
        );
        self::assertFalse(
            $matcher->match(10),
            "'10' must not satisfy '< 5'.",
        );
    }
}
