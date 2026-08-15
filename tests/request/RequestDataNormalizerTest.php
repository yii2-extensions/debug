<?php

declare(strict_types=1);

namespace yii\debug\tests\request;

use PHPForge\Debug\Storage\RequestSummary;
use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\request\RequestDataNormalizer;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see RequestDataNormalizer} covering captured request data plus the controller summary
 * into the typed {@see \yii\debug\panels\request\RequestView} aggregate (hero header + tab/section list).
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
            null,
        );

        self::assertSame(
            ['AJAX', 'Flash', 'HTTPS'],
            $view->hero->flags,
            'Active flags must surface in declaration order.',
        );
    }

    public function testFromPanelDataCoercesNumericStringStatusCodeToInt(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            ['statusCode' => '404'],
            null,
        );

        self::assertSame(
            404,
            $view->hero->statusCode,
            'Numeric-string statusCode must coerce to int.',
        );
    }

    public function testFromPanelDataDropsServerTabWhenServerKeyMissing(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            [],
            null,
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
            null,
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
            null,
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

    public function testFromPanelDataFallsBackToEmptyViewWhenDataIsEmpty(): void
    {
        $view = RequestDataNormalizer::fromPanelData(
            [],
            null,
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
        foreach ([200 => '2xx', 304 => '3xx', 404 => '4xx', 500 => '5xx', 0 => 'none'] as $code => $expected) {
            $view = RequestDataNormalizer::fromPanelData(
                ['statusCode' => $code],
                null,
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
            null,
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
            self::summary(['statusCode' => 500]),
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
            null,
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
            self::summary(['ip' => '127.0.0.1', 'time' => 1_704_112_496.0, 'processingTime' => 0.0125]),
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
            null,
        );

        self::assertSame(
            ['HTTPS'],
            $view->hero->flags,
            "Only literal 'true' must enable a flag; truthy non-bools count as inactive.",
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private static function summary(array $overrides = []): RequestSummary
    {
        return RequestSummary::fromArray(
            [
                'tag' => 'tag-1',
                'url' => 'https://example.test/',
                'ajax' => false,
                'method' => 'GET',
                'ip' => '127.0.0.1',
                'time' => 1_700_000_000.0,
                'statusCode' => 200,
                'sqlCount' => 0,
                'excessiveCallersCount' => 0,
                'mailCount' => 0,
                'mailFiles' => [],
                'processingTime' => null,
                'peakMemory' => null,
                ...$overrides,
            ],
        );
    }
}
