<?php

declare(strict_types=1);

namespace yiiunit\debug;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\request\RequestDataNormalizer;

/**
 * Unit tests for {@see RequestDataNormalizer} covering the narrowing of `$panel->data` plus the controller summary
 * into the typed {@see \yii\debug\panels\request\RequestView} aggregate (hero header + tab/section list).
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('panel')]
#[Group('request')]
final class RequestDataNormalizerTest extends TestCase
{
    public function testFromPanelDataAccumulatesActiveFlagsInDeclarationOrder(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            [
                'general' => [
                    'isAjax' => true,
                    'isPjax' => false,
                    'isFlash' => true,
                    'isSecureConnection' => true,
                ],
            ],
            [],
        );

        self::assertSame(
            ['AJAX', 'Flash', 'HTTPS'],
            $view->hero->flags,
            'Active flags must surface in declaration order.',
        );
    }

    public function testFromPanelDataDropsServerTabWhenServerKeyMissing(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            [],
            [],
        );

        $labels = [];

        foreach ($view->tabs as $tab) {
            $labels[] = $tab->label;
        }

        self::assertSame(
            ['Parameters', 'Headers'],
            $labels,
            'Missing SERVER bucket must collapse the tab strip to the base pair.',
        );
    }

    public function testFromPanelDataDropsSessionTabWhenSessionOrFlashesMissing(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            ['SERVER' => []],
            [],
        );

        $labels = [];

        foreach ($view->tabs as $tab) {
            $labels[] = $tab->label;
        }

        self::assertNotContains(
            'Session',
            $labels,
            'Without SESSION + flashes the Session tab must not surface.',
        );
    }

    public function testFromPanelDataExposesEveryTabWhenSessionAndServerArePresent(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            [
                'SESSION' => ['user' => 1],
                'flashes' => [],
                'SERVER' => ['HTTP_HOST' => 'localhost'],
            ],
            [],
        );

        $labels = [];

        foreach ($view->tabs as $tab) {
            $labels[] = $tab->label;
        }

        self::assertSame(
            ['Parameters', 'Headers', 'Session', 'Server'],
            $labels,
            'All four tabs must surface when SESSION + SERVER exist.',
        );
    }

    public function testFromPanelDataFallsBackToEmptyViewWhenDataIsNotArray(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            'not-an-array',
            [],
        );

        self::assertSame(
            '',
            $view->hero->method,
            'Non-array data must yield an empty hero method.',
        );
        self::assertSame(
            0,
            $view->hero->statusCode,
            'Non-array data must yield a zero status code.'
        );
        self::assertSame(
            [],
            $view->hero->flags,
            'Non-array data must yield zero flags.',
        );
        self::assertCount(
            2,
            $view->tabs,
            'Non-array data must still produce the base Parameters + Headers tabs.',
        );
    }

    public function testFromPanelDataMapsHttpStatusToVariantBucket(): void
    {
        foreach ([200 => 'success', 304 => 'muted', 404 => 'warning', 500 => 'danger', 0 => 'muted'] as $code => $expected) {
            $view = RequestDataNormalizer::fromPanelData(
                ['statusCode' => $code],
                [],
            );

            self::assertSame(
                $expected,
                $view->hero->statusVariant,
                "Status {$code} must map to the {$expected} variant.",
            );
        }
    }

    public function testFromPanelDataParametersTabExposesEveryOptionalBucketWhenPresent(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            [
                'route' => 'site/index',
                'action' => 'SiteController::actionIndex()',
                'actionParams' => [],
                'GET' => ['q' => 'x'],
                'POST' => ['x' => 1],
                'FILES' => [],
                'COOKIE' => ['session' => 'abc'],
                'requestBody' => [],
            ],
            [],
        );

        self::assertNotEmpty(
            $view->tabs,
            'Tabs must be present.',
        );

        $captions = [];

        foreach ($view->tabs[0]->sections as $section) {
            $captions[] = $section->caption;
        }

        self::assertSame(
            ['Routing', 'Get', 'Post', 'Files', 'Cookies', 'Request Body'],
            $captions,
            'Parameters tab must include every optional bucket that exists in the payload.',
        );
    }

    public function testFromPanelDataPrefersPanelStatusCodeOverSummary(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            ['statusCode' => 201],
            ['statusCode' => 500],
        );

        self::assertSame(
            201,
            $view->hero->statusCode,
            'Panel data must override the controller summary status.',
        );
    }

    public function testFromPanelDataRoutingSectionAlwaysHasThreeEntries(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            [],
            [],
        );

        self::assertNotEmpty(
            $view->tabs,
            'Parameters tab must always be present.',
        );
        self::assertNotEmpty(
            $view->tabs[0]->sections,
            'Parameters tab must contain the Routing section.',
        );

        $routing = $view->tabs[0]->sections[0];

        self::assertSame(
            'Routing',
            $routing->caption,
            'First parameters section must be the Routing block.',
        );
        self::assertSame(
            ['Route', 'Action', 'Parameters'],
            array_keys($routing->entries),
            'Routing keys must follow Route/Action/Parameters.',
        );
    }

    public function testFromPanelDataSurfacesIpTimeAndDurationFromSummary(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            [],
            [
                'ip' => '127.0.0.1',
                'time' => 1_704_112_496,
                'processingTime' => 0.0125,
            ]
        );

        self::assertSame(
            '127.0.0.1',
            $view->hero->ip,
            'Summary ip must surface on the hero meta strip.',
        );
        self::assertMatchesRegularExpression(
            '/^\d{2}:\d{2}:\d{2}$/',
            $view->hero->time,
            "Time must format as 'HH:MM:SS'.",
        );
        self::assertSame(
            '12.5 ms',
            $view->hero->durationMs,
            "Duration must format as 'X.X ms'.",
        );
    }

    public function testFromPanelDataTreatsNonBoolFlagAsInactive(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            [
                'general' => [
                    'isAjax' => 1,
                    'isPjax' => 'yes',
                    'isFlash' => null,
                    'isSecureConnection' => true,
                ],
            ],
            [],
        );

        self::assertSame(
            ['HTTPS'],
            $view->hero->flags,
            "Only literal 'true' must enable a flag; truthy non-bools count as inactive.",
        );
    }
}
