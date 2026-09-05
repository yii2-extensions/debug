<?php

declare(strict_types=1);

namespace yii\debug\tests\request;

use PHPForge\Debug\Panel\Request\RequestSnapshot;
use PHPForge\Debug\Panel\Router\RouterSnapshot;
use PHPForge\Debug\Storage\{ExceptionSnapshot, HydrationException};
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use RuntimeException;
use Yii;
use yii\debug\panels\{RequestPanel, RouterPanel};
use yii\debug\tests\provider\RequestPanelProvider;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;
use yii\web\Controller;

/**
 * Unit tests for {@see RequestPanel} covering detail rendering, the toolbar status chip, and snapshot hydration.
 *
 * {@see RequestPanelProvider} for test case data providers.
 */
#[Group('panel')]
#[Group('request')]
final class RequestPanelTest extends TestCase
{
    public function testGetDetailComposesRouteOverviewCanonicalTabsAndRouterDiagnostics(): void
    {
        $panel = $this->makePanel(
            RequestPanel::class,
            [
                'urlManager' => [
                    'enablePrettyUrl' => true,
                    'enableStrictParsing' => true,
                    'showScriptName' => false,
                    'suffix' => '.html',
                    'rules' => [
                        [
                            'pattern' => 'orders/<id:\\d+>',
                            'route' => 'orders/view',
                            'suffix' => '.json',
                            'verb' => ['GET', 'POST'],
                        ],
                    ],
                ],
            ],
        );
        $router = $panel->module?->panels['router'] ?? null;

        self::assertInstanceOf(
            RouterPanel::class,
            $router,
            'The built-in hidden Router panel must remain available.',
        );

        $this->hydratePanel(
            $router,
            RouterSnapshot::capture(
                'app\\controllers\\OrderController::actionView()',
                [
                    [
                        ['rule' => 'GET,POST orders/<id:\\d+>', 'match' => true, 'parent' => ''],
                        Logger::LEVEL_TRACE,
                    ],
                ],
                'orders/view',
            ),
        );
        $this->hydratePanel(
            $panel,
            RequestSnapshot::capture(
                [
                    'route' => 'orders/view',
                    'action' => 'app\\controllers\\OrderController::actionView()',
                    'actionParams' => ['id' => '42'],
                    'statusCode' => 200,
                    'general' => ['method' => 'GET'],
                    'requestHeaders' => [],
                    'responseHeaders' => [],
                    'flashes' => [],
                    'GET' => ['filter' => 'open'],
                    'POST' => [],
                    'COOKIE' => [],
                    'FILES' => [],
                    'SERVER' => [],
                    'SESSION' => [],
                ],
            ),
        );

        $detail = $panel->getDetail();

        self::assertStringContainsString(
            'yii-debug-request-overview',
            $detail,
            'Request must render the shared persistent overview.',
        );
        self::assertStringContainsString(
            'orders/view',
            $detail,
            'The overview must expose the resolved route.',
        );
        self::assertStringContainsString(
            'app\\controllers\\OrderController::actionView()',
            $detail,
            'The overview must expose the dispatched action.',
        );
        self::assertStringContainsString(
            'orders/&lt;id:\\d+&gt;',
            $detail,
            'The Routes tab must expose the escaped rule pattern.',
        );
        self::assertStringContainsString(
            'Pretty URL Enabled',
            $detail,
            'Router configuration badges must be present.',
        );
        self::assertStringContainsString(
            'Strict Parsing Enabled',
            $detail,
            'Strict-parsing state must be represented as a configuration badge.',
        );
        self::assertStringContainsString(
            'Global Suffix: .html',
            $detail,
            'The global suffix badge must be present.',
        );
        self::assertStringContainsString(
            'GET,POST orders/&lt;id:\\d+&gt;',
            $detail,
            'The captured URL-rule trace must be composed into Request.',
        );
        self::assertStringContainsString(
            'Matched',
            $detail,
            'The successful route probe must be identified textually.',
        );
        self::assertStringContainsString(
            'Source: Current URL manager configuration. Live configuration may differ from this capture.',
            $detail,
            'Live route provenance must be explicit.',
        );

        $positions = [];

        foreach (['Input', 'Headers', 'Session', 'Routes (1)', 'Server'] as $label) {
            $position = strpos($detail, ">{$label}<");

            self::assertNotFalse($position, "The '{$label}' canonical Request tab must be rendered.");

            $positions[] = $position;
        }

        self::assertTrue(
            $positions[0] < $positions[1]
            && $positions[1] < $positions[2]
            && $positions[2] < $positions[3]
            && $positions[3] < $positions[4],
            'Canonical Request tabs must be ordered Input, Headers, Session, Routes, Server.',
        );
    }

    public function testGetDetailConsumesSharedHeaderAndServerDiagnostics(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $this->hydratePanel(
            $panel,
            RequestSnapshot::capture(
                [
                    'route' => 'site/index',
                    'statusCode' => 200,
                    'general' => ['method' => 'GET'],
                    'GET' => ['page' => '2'],
                    'requestHeaders' => [
                        'accept' => ['text/html', 'application/json'],
                        'x-empty' => '',
                    ],
                    'responseHeaders' => [
                        'Vary' => ['Accept-Encoding', 'X-Inertia'],
                        0 => 'HTTP/1.1 200 OK',
                    ],
                    'SERVER' => [
                        'REQUEST_METHOD' => 'GET',
                        'SERVER_PROTOCOL' => 'HTTP/1.1',
                        'REMOTE_ADDR' => '::1',
                        'DOCUMENT_ROOT' => '/srv/app/public',
                        'SCRIPT_FILENAME' => '/srv/app/public/index.php',
                        'HTTP_ACCEPT' => 'text/html, application/json',
                        'APP_ENV' => 'debug',
                        'CUSTOM_LEGACY' => ['nested' => '<value>'],
                    ],
                ],
            ),
        );

        $detail = $panel->getDetail();

        self::assertStringContainsString(
            'yii-debug-header-exchange',
            $detail,
            'Request must consume the shared directional header renderer.',
        );
        self::assertMatchesRegularExpression(
            '~Header exchange.*Inbound.*Request headers.*accept.*2 values.*text/html.*application/json'
            . '.*Outbound.*Response headers.*Vary.*Accept-Encoding.*X-Inertia.*Raw response line 0'
            . '.*HTTP/1\.1 200 OK~s',
            $detail,
            'Yii2 header shapes must retain repeated values and raw SAPI response lines in exchange order.',
        );
        self::assertStringContainsString(
            'aria-label="Filter request and response headers"',
            $detail,
            'The shared header exchange must expose one cross-direction filter.',
        );
        self::assertStringContainsString(
            'yii-debug-server-environment',
            $detail,
            'Request must consume the shared grouped server renderer.',
        );
        self::assertMatchesRegularExpression(
            '~Server details.*Network &amp; transport.*Runtime &amp; paths'
            . '.*Additional header variables.*Environment &amp; other.*Raw server variables~s',
            $detail,
            'Yii2 must retain additional diagnostics and the complete raw view.',
        );
        self::assertStringNotContainsString(
            'Execution context',
            $detail,
            'The redundant server summary must be absent.',
        );
        self::assertMatchesRegularExpression(
            '~<details(?=[^>]*aria-label="Raw server variables")(?![^>]*\sopen(?:\s|=|>))[^>]*>~',
            $detail,
            'Raw variables must start collapsed.',
        );
        self::assertSame(
            5,
            substr_count($detail, 'class="yii-debug-server-group yii-debug-server-group-disclosure"'),
            'Four additional groups and the raw view must remain available.',
        );
        self::assertStringContainsString(
            'aria-label="Filter Raw server variables"',
            $detail,
            'The raw view needs its own filter.',
        );
        self::assertMatchesRegularExpression(
            '~<details class="yii-debug-disclosure" open>\s*<summary[^>]*>\s*'
            . '<span class="yii-debug-disclosure-title">Get</span>~s',
            $detail,
            'A populated Yii2 Input bucket must start open.',
        );
        self::assertStringContainsString(
            '&lt;value&gt;',
            $detail,
            'Malformed legacy SERVER values must remain inspectable and escaped.',
        );
    }

    public function testGetDetailEscapesHiddenRouterFailure(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $router = $panel->module?->panels['router'] ?? null;

        self::assertInstanceOf(
            RouterPanel::class,
            $router,
            'The built-in hidden Router panel must remain available.',
        );

        $router->setError(
            ExceptionSnapshot::fromThrowable(new RuntimeException('<script>alert("router")</script>')),
        );

        $this->hydratePanel(
            $panel,
            RequestSnapshot::capture(
                [
                    'route' => 'site/index',
                    'statusCode' => 500,
                    'general' => ['method' => 'GET'],
                    'requestHeaders' => [],
                    'responseHeaders' => [],
                ],
            ),
        );

        $detail = $panel->getDetail();

        self::assertStringContainsString(
            '&lt;script&gt;alert("router")&lt;/script&gt;',
            $detail,
            'A Router failure must remain readable after HTML escaping.',
        );
        self::assertStringNotContainsString(
            '<script>alert("router")</script>',
            $detail,
            'A Router failure must never become executable markup.',
        );
    }

    public function testGetDetailRendersWithCapturedData(): void
    {
        $panel = $this->makePanel(
            RequestPanel::class,
        );

        $this->hydratePanel(
            $panel,
            RequestSnapshot::capture(
                [
                    'route' => 'site/index',
                    'statusCode' => 200,
                    'general' => ['method' => 'GET'],
                    'requestHeaders' => [],
                    'responseHeaders' => [],
                    'GET' => [],
                    'POST' => [],
                    'COOKIE' => [],
                    'FILES' => [],
                    'SERVER' => [],
                    'SESSION' => [],
                ],
            ),
        );

        self::assertNotEmpty(
            $panel->getDetail(),
            'Detail view must produce markup.',
        );
    }

    public function testGetDetailUsesEmptySummaryWhenRequestedActionIsNotADebugAction(): void
    {
        $panel = $this->makePanel(
            RequestPanel::class,
        );

        Yii::$app->controller = new Controller('plain', Yii::$app);

        $this->hydratePanel(
            $panel,
            RequestSnapshot::capture(
                [
                    'route' => 'site/index',
                    'statusCode' => 200,
                    'general' => ['method' => 'GET'],
                    'requestHeaders' => [],
                    'responseHeaders' => [],
                ],
            ),
        );

        self::assertNotEmpty(
            $panel->getDetail(),
            'Non-debug dispatch must fall back to an empty summary.',
        );
    }

    public function testGetDetailWorksWithoutAnOwningModule(): void
    {
        $this->mockWebApplication();

        self::assertNotEmpty(
            (new RequestPanel())->getDetail(),
            'Request must degrade to an empty composed view before module wiring or snapshot hydration.',
        );
    }

    public function testGetNameAndIcon(): void
    {
        $panel = $this->makePanel(
            RequestPanel::class,
        );

        self::assertSame(
            'Request',
            $panel->getName(),
            "Display name must be 'Request'.",
        );
        self::assertSame(
            'request',
            $panel->getToolbarIcon(),
            "Icon key must be 'request'.",
        );
    }

    public function testGetStatusCodeFallsBackTo200ForNonArrayData(): void
    {
        $panel = $this->makePanel(
            RequestPanel::class,
        );

        self::assertSame(
            200,
            $this->invoke(
                $panel,
                'getStatusCode',
            ),
            "Non-array data must default to '200'.",
        );
        self::assertSame(
            [
                [
                    'value' => '200',
                    'status' => 'status-2xx',
                    'title' => 'Status code: 200 OK',
                    'id' => 'status',
                ],
            ],
            $this->invoke($panel, 'getToolbarItems'),
            'An unhydrated Request panel must still produce its safe default status item.',
        );
    }

    public function testGetStatusCodeReturnsIntStatusCode(): void
    {
        $panel = $this->makePanel(
            RequestPanel::class,
        );

        $this->hydratePanel(
            $panel,
            RequestSnapshot::capture(['statusCode' => 500]),
        );

        self::assertSame(
            500,
            $this->invoke(
                $panel,
                'getStatusCode',
            ),
            'Int status must be returned verbatim.',
        );
    }

    public function testGetToolbarItemsOmitsEmptyOrNonStringRoutes(): void
    {
        $panel = $this->makePanel(
            RequestPanel::class,
        );

        foreach (['', null, 42] as $route) {
            $this->hydratePanel(
                $panel,
                RequestSnapshot::capture(['route' => $route, 'statusCode' => 200]),
            );

            $items = $this->invoke(
                $panel,
                'getToolbarItems',
            );

            self::assertIsArray(
                $items,
                'Toolbar items must remain an array.',
            );
            self::assertCount(
                1,
                $items,
                'Only a non-empty string route may add a toolbar metric.',
            );

            $item = $items[0] ?? self::fail('Expected the status toolbar item.');

            self::assertIsArray(
                $item,
                'Toolbar item must remain an array.',
            );
            self::assertSame(
                '200',
                $item['value'] ?? null,
                'Status must remain when the route is omitted.',
            );
        }
    }

    public function testGetToolbarItemsRendersResolvedRouteBeforeStatus(): void
    {
        $panel = $this->makePanel(
            RequestPanel::class,
        );

        $this->hydratePanel(
            $panel,
            RequestSnapshot::capture(['route' => 'orders/view', 'statusCode' => 200]),
        );

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertSame(
            [
                [
                    'value' => 'orders/view',
                    'status' => 'default',
                    'title' => 'Resolved route: orders/view',
                    'id' => 'route',
                ],
                [
                    'value' => '200',
                    'status' => 'status-2xx',
                    'title' => 'Status code: 200 OK',
                    'id' => 'status',
                ],
            ],
            $items,
            'Resolved route and response status must share one Request group in that order.',
        );
    }

    public function testGetToolbarItemsRendersStatus2xxForSuccess(): void
    {
        $panel = $this->makePanel(
            RequestPanel::class,
        );

        $this->hydratePanel(
            $panel,
            RequestSnapshot::capture(['statusCode' => 201]),
        );

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Toolbar items must be an array.',
        );

        $first = $items[0] ?? self::fail('Expected one item.');

        self::assertIsArray(
            $first,
            'Item must be an array.',
        );
        self::assertSame(
            'status-2xx',
            $first['status'] ?? null,
            "2xx must carry the 'status-2xx' badge.",
        );

        $title = $first['title'] ?? '';

        self::assertIsString(
            $title,
            'Title must be a string.',
        );
        self::assertStringContainsString(
            'Status code: 201',
            $title,
            'Title must include the captured status code.',
        );
    }

    public function testGetToolbarItemsRendersStatus3xxForRedirects(): void
    {
        $panel = $this->makePanel(
            RequestPanel::class,
        );

        $this->hydratePanel(
            $panel,
            RequestSnapshot::capture(['statusCode' => 302]),
        );

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Toolbar items must be an array.',
        );

        $first = $items[0] ?? self::fail('Expected one item.');

        self::assertIsArray(
            $first,
            'Item must be an array.',
        );
        self::assertSame(
            'status-3xx',
            $first['status'] ?? null,
            "Redirects must carry the 'status-3xx' badge.",
        );
    }

    public function testGetToolbarItemsRendersStatus5xxForServerErrors(): void
    {
        $panel = $this->makePanel(
            RequestPanel::class,
        );

        $this->hydratePanel(
            $panel,
            RequestSnapshot::capture(['statusCode' => 500]),
        );

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Toolbar items must be an array.',
        );

        $first = $items[0] ?? self::fail('Expected one item.');

        self::assertIsArray(
            $first,
            'Item must be an array.',
        );
        self::assertSame(
            'status-5xx',
            $first['status'] ?? null,
            "Server errors must carry the 'status-5xx' badge.",
        );
        self::assertSame(
            '500',
            $first['value'] ?? null,
            'Value must echo the captured status code.',
        );
    }

    public function testGetToolbarItemsTreatsUnknownStatusTextAsEmpty(): void
    {
        $panel = $this->makePanel(
            RequestPanel::class,
        );

        $this->hydratePanel(
            $panel,
            RequestSnapshot::capture(['statusCode' => 299]),
        );

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Toolbar items must be an array.',
        );

        $first = $items[0] ?? self::fail('Expected one item.');

        self::assertIsArray(
            $first,
            'Item must be an array.',
        );
        self::assertSame(
            'Status code: 299',
            $first['title'] ?? null,
            'Unknown status code must omit the missing reason phrase cleanly.',
        );
    }

    #[DataProviderExternal(RequestPanelProvider::class, 'invalidStoredStatusCodes')]
    public function testThrowHydrationExceptionForInvalidStoredStatusCode(string $statusCode): void
    {
        $panel = $this->makePanel(
            RequestPanel::class,
        );

        $payload = RequestSnapshot::capture(['statusCode' => 200])->jsonSerialize();

        $payload['statusCode'] = $statusCode;

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '$.panels..statusCode': expected an integer.",
        );

        $panel->hydrate($payload);
    }

    public function testThrowHydrationExceptionWhenCapturedDataCarriesNoIntegerStatusCode(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '\$.panels.request.statusCode'",
        );

        RequestSnapshot::capture(['statusCode' => '200']);
    }

    public function testThrowHydrationExceptionWhenTheStatusCodeDisagreesWithTheStoredData(): void
    {
        $payload = RequestSnapshot::capture(['statusCode' => 200])->jsonSerialize();

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '\$.panels.request.statusCode'",
        );

        RequestSnapshot::fromArray([...$payload, 'statusCode' => 404], '$.panels.request');
    }
}
