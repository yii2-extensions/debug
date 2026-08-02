<?php

declare(strict_types=1);

namespace yii\debug\tests\router;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\router\{ActionRouteRow, CurrentRouteLogRow, RouterRuleRow};
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for the typed row DTOs powering the Router panel detail tables: {@see RouterRuleRow},
 * {@see ActionRouteRow}, {@see CurrentRouteLogRow}. Covers loose-array narrowing, verb-list joining, and the
 * literal-`true` match flag.
 */
#[Group('panel')]
#[Group('router')]
final class RouterRowsTest extends TestCase
{
    public function testActionRouteRowCoercesNumericCountToInt(): void
    {
        $row = ActionRouteRow::from('site/index', ['route' => 'site/index', 'rule' => '', 'count' => '7']);

        self::assertSame(
            7,
            $row->count,
            'Numeric string count must coerce to int.',
        );
    }

    public function testActionRouteRowFallsBackToZeroCountWhenMissing(): void
    {
        $row = ActionRouteRow::from('site/index', ['route' => 'site/index']);

        self::assertSame(
            0,
            $row->count,
            "Missing count must default to '0'.",
        );
    }

    public function testActionRouteRowKeepsActionFromKey(): void
    {
        $row = ActionRouteRow::from('app\\controllers\\SiteController::actionIndex', ['route' => 'site/index']);

        self::assertSame(
            'app\\controllers\\SiteController::actionIndex',
            $row->action,
            'Action FQCN must survive verbatim from the row key.',
        );
    }

    public function testCurrentRouteLogRowIsBuiltOnlyFromLiteralBooleanMatch(): void
    {
        $matched = CurrentRouteLogRow::fromLogMessage(['rule' => 'r', 'match' => true]);

        self::assertNotNull($matched, 'A literal boolean match yields a row.');
        self::assertTrue($matched->match, "Literal 'true' must mark a row as matched.");
        self::assertNull(
            CurrentRouteLogRow::fromLogMessage(['rule' => 'r', 'match' => 1]),
            'Truthy non-bool must be rejected outright.',
        );
        self::assertNull(
            CurrentRouteLogRow::fromLogMessage(['rule' => 'r']),
            'A missing match must be rejected outright.',
        );
    }

    public function testCurrentRouteLogRowParentFallsBackToEmptyWhenMissing(): void
    {
        $row = CurrentRouteLogRow::fromLogMessage(['rule' => 'app\\rules\\Home', 'match' => false]);

        self::assertNotNull($row, 'A well-formed payload yields a row.');
        self::assertSame('app\\rules\\Home', $row->rule, 'Rule name must round-trip.');
        self::assertSame('', $row->parent, 'A missing parent must fall back to an empty string.');
    }

    public function testRouterRuleRowFallsBackToEmptyStringsOnMissingKeys(): void
    {
        $row = RouterRuleRow::from([]);

        self::assertSame(
            '',
            $row->name,
            'Missing name must fall back to empty string.',
        );
        self::assertSame(
            '',
            $row->route,
            'Missing route must fall back to empty string.',
        );
        self::assertSame(
            '',
            $row->verb,
            'Missing verb must fall back to empty string.',
        );
        self::assertSame(
            '',
            $row->suffix,
            'Missing suffix must fall back to empty string.',
        );
        self::assertSame(
            '',
            $row->mode,
            'Missing mode must fall back to empty string.',
        );
        self::assertSame(
            '',
            $row->type,
            'Missing type must fall back to empty string.',
        );
    }

    public function testRouterRuleRowJoinsVerbArrayWithComma(): void
    {
        $row = RouterRuleRow::from(['verb' => ['GET', 'POST', 'PUT']]);

        self::assertSame(
            'GET, POST, PUT',
            $row->verb,
            "Verb list must join with ', ' separator.",
        );
    }

    public function testRouterRuleRowKeepsScalarVerbVerbatim(): void
    {
        $row = RouterRuleRow::from(['verb' => 'GET']);

        self::assertSame(
            'GET',
            $row->verb,
            'Scalar verb must survive verbatim.',
        );
    }
}
