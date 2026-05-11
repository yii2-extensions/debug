<?php

declare(strict_types=1);

namespace yiiunit\debug;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\router\{ActionRouteRow, CurrentRouteLogRow, RouterRuleRow};

/**
 * Unit tests for the typed row DTOs powering the Router panel detail tables: {@see RouterRuleRow},
 * {@see ActionRouteRow}, {@see CurrentRouteLogRow}. Covers loose-array narrowing, verb-list joining, and the
 * literal-`true` match flag.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
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

    public function testCurrentRouteLogRowMatchFlagIsTrueOnlyForLiteralTrue(): void
    {
        self::assertTrue(
            CurrentRouteLogRow::from(['rule' => 'r', 'match' => true])->match,
            "Literal 'true' must mark a row as matched."
        );
        self::assertFalse(
            CurrentRouteLogRow::from(['rule' => 'r', 'match' => 1])->match,
            'Truthy non-bool must NOT mark the row as matched.'
        );
        self::assertFalse(
            CurrentRouteLogRow::from(['rule' => 'r'])->match,
            "Missing match must default to 'false'."
        );
    }

    public function testCurrentRouteLogRowParentFallsBackToEmptyWhenMissing(): void
    {
        $row = CurrentRouteLogRow::from(['rule' => 'app\\rules\\Home']);

        self::assertSame(
            '',
            $row->parent,
            'Missing parent must default to empty string.',
        );
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
