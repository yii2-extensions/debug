<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\base\{Action, InlineAction};
use yii\debug\collectors\RequestCollector;
use yii\debug\tests\support\TestCase;
use yii\web\{Controller, Session};

/**
 * Unit tests for {@see RequestCollector} covering header capture, action narrowing, censor masking, response-header
 * aggregation, flash retrieval, superglobal normalization, and the startup/shutdown lifecycle.
 */
#[Group('collector')]
#[Group('request')]
final class RequestCollectorTest extends TestCase
{
    public function testCaptureBuildsActionFromInlineAction(): void
    {
        $collector = $this->makeCollector();

        $controller = new Controller('site', Yii::$app);
        $action = new InlineAction('index', $controller, 'actionIndex');

        Yii::$app->requestedAction = $action;

        $saved = $this->captureData($collector);

        self::assertSame(
            $controller::class . '::actionIndex()',
            $saved['action'] ?? null,
            "Inline action must format as 'ControllerFQCN::actionMethod()'.",
        );
        self::assertSame(
            'site/index',
            $saved['route'] ?? null,
            'Route must echo the action unique id.',
        );
    }

    public function testCaptureBuildsActionFromRegularAction(): void
    {
        $collector = $this->makeCollector();

        $controller = new Controller('site', Yii::$app);

        $action = new class ('run', $controller) extends Action {
            public function run(): void {}
        };

        Yii::$app->requestedAction = $action;

        $saved = $this->captureData($collector);

        self::assertSame(
            $action::class . '::run()',
            $saved['action'] ?? null,
            "Regular action must format as 'ActionFQCN::run()'.",
        );
    }

    public function testCaptureCapturesRequestBodyWhenNonEmpty(): void
    {
        $collector = $this->makeCollector();

        $request = Yii::$app->getRequest();

        $request->setRawBody('{"k":"v"}');
        $request->setBodyParams(['k' => 'v']);
        $request->getHeaders()->set('Content-Type', 'application/json');

        $saved = $this->captureData($collector);

        self::assertIsArray(
            $saved['requestBody'] ?? null,
            'Request body must surface as an array when non-empty.',
        );
        self::assertSame(
            '{"k":"v"}',
            $saved['requestBody']['Raw'] ?? null,
            'Raw slot must echo the raw body.',
        );
    }

    public function testCaptureCensorsRequestHeadersListedInCensoredVariableNames(): void
    {
        $collector = $this->makeCollector();

        $collector->censoredVariableNames = ['authorization'];

        Yii::$app->getRequest()->getHeaders()->set('Authorization', 'Bearer secret');

        $saved = $this->captureData($collector);

        $requestHeaders = $saved['requestHeaders'] ?? null;

        self::assertIsArray(
            $requestHeaders,
            "'requestHeaders' slot must be an array.",
        );
        self::assertSame(
            '****',
            $requestHeaders['authorization'] ?? null,
            'Censored request header must be masked.',
        );
    }

    public function testCaptureCollapsesSingleValueHeaderArrayToScalar(): void
    {
        $collector = $this->makeCollector();

        Yii::$app->getRequest()->getHeaders()->set('X-Single', 'only');

        $saved = $this->captureData($collector);

        $requestHeaders = $saved['requestHeaders'] ?? null;

        self::assertIsArray(
            $requestHeaders,
            "'requestHeaders' slot must be an array.",
        );
        self::assertSame(
            'only',
            $requestHeaders['x-single'] ?? null,
            'Single-value header must collapse to the scalar value.',
        );
    }

    public function testCaptureKeepsMultiValueHeaderAsArray(): void
    {
        $collector = $this->makeCollector();

        $request = Yii::$app->getRequest();

        $request->getHeaders()->add('X-Multi', 'a')->add('X-Multi', 'b');

        $saved = $this->captureData($collector);

        $requestHeaders = $saved['requestHeaders'] ?? null;

        self::assertIsArray(
            $requestHeaders,
            "'requestHeaders' slot must be an array.",
        );
        self::assertSame(
            ['a', 'b'],
            $requestHeaders['x-multi'] ?? null,
            'Multi-value header must stay as a list.',
        );
    }

    public function testCaptureLeavesActionAsNullWhenNoRequestedAction(): void
    {
        $collector = $this->makeCollector();

        Yii::$app->requestedAction = null;
        Yii::$app->requestedRoute = 'site/default';

        $saved = $this->captureData($collector);

        self::assertArrayHasKey(
            'action',
            $saved,
            "Action slot must be present even when 'null'.",
        );
        self::assertNull(
            $saved['action'],
            "Missing requested action must yield 'null'.",
        );
        self::assertSame(
            'site/default',
            $saved['route'] ?? null,
            'Route must fall back to requestedRoute.',
        );
    }

    public function testCaptureLeavesRequestBodyEmptyWhenRawBodyIsEmpty(): void
    {
        $collector = $this->makeCollector();

        Yii::$app->getRequest()->setRawBody('');

        $saved = $this->captureData($collector);

        self::assertSame(
            [],
            $saved['requestBody'] ?? null,
            "Empty raw body must collapse to '[]'.",
        );
    }

    public function testCaptureReturnsNullAfterShutdown(): void
    {
        $collector = $this->makeCollector();

        $collector->shutdown();

        self::assertNull(
            $collector->capture(),
            'Stopped collector must record nothing.',
        );
    }

    public function testCaptureReturnsNullBeforeStartup(): void
    {
        $this->mockWebApplication();

        self::assertNull(
            (new RequestCollector())->capture(),
            'Idle collector must record nothing.',
        );
    }

    public function testCaptureSurfacesConfiguredDisplayVars(): void
    {
        $collector = $this->makeCollector();

        $collector->displayVars = ['_GET'];
        $GLOBALS['_GET'] = ['q' => 'search-term'];

        $saved = $this->captureData($collector);

        self::assertSame(
            ['q' => 'search-term'],
            $saved['GET'] ?? null,
            'Configured displayVar must surface under its trimmed key.',
        );
    }

    public function testCensorArrayLeavesUnmatchedKeysUntouched(): void
    {
        $collector = $this->makeCollector();

        $collector->censoredVariableNames = ['Authorization'];

        $masked = $this->invoke(
            $collector,
            'censorArray',
            [
                [
                    'route' => 'site/index',
                    'statusCode' => 200,
                ],
            ],
        );

        self::assertSame(
            ['route' => 'site/index', 'statusCode' => 200],
            $masked,
            'Unmatched keys must round-trip unchanged.',
        );
    }

    public function testCensorArrayMasksMatchedTopLevelKey(): void
    {
        $collector = $this->makeCollector();

        $collector->censoredVariableNames = ['POST'];

        $masked = $this->invoke(
            $collector,
            'censorArray',
            [
                [
                    'POST' => ['password' => 'secret'],
                    'route' => 'site/login',
                ],
            ],
        );

        self::assertIsArray(
            $masked,
            'Censored payload must be an array.',
        );
        self::assertSame(
            '****',
            $masked['POST'] ?? null,
            'Matched key must be replaced by the censor string.',
        );
        self::assertSame(
            'site/login',
            $masked['route'] ?? null,
            'Non-matching keys must round-trip unchanged.',
        );
    }

    public function testCensorArrayMasksRequestBodyRawWhenRequestBodyKeyCensored(): void
    {
        $collector = $this->makeCollector();

        $collector->censoredVariableNames = ['requestBody.Decoded'];

        $masked = $this->invoke(
            $collector,
            'censorArray',
            [
                [
                    'requestBody' => [
                        'Content Type' => 'application/json',
                        'Decoded' => ['password' => 'secret'],
                        'Raw' => '{"password":"secret"}',
                    ],
                ],
            ],
        );

        self::assertIsArray(
            $masked,
            'Censored payload must be an array.',
        );

        $requestBody = $masked['requestBody'] ?? null;

        self::assertIsArray(
            $requestBody,
            'requestBody slot must be an array.',
        );
        self::assertSame(
            '****',
            $requestBody['Decoded'] ?? null,
            'Decoded slot must be masked.',
        );
        self::assertSame(
            '****',
            $requestBody['Raw'] ?? null,
            'Raw slot must be masked when any requestBody.* key is censored.',
        );
    }

    public function testCensorArrayReturnsEarlyWhenDataEmpty(): void
    {
        $collector = $this->makeCollector();

        $collector->censoredVariableNames = ['Authorization'];

        self::assertSame(
            [],
            $this->invoke(
                $collector,
                'censorArray',
                [[]],
            ),
            "Empty data must short-circuit to '[]'.",
        );
    }

    public function testCensorArrayReturnsEarlyWhenNoCensorList(): void
    {
        $collector = $this->makeCollector();

        $masked = $this->invoke(
            $collector,
            'censorArray',
            [
                ['route' => 'site/index'],
            ],
        );

        self::assertSame(
            ['route' => 'site/index'],
            $masked,
            'Empty censor list must short-circuit to the original payload.',
        );
    }

    public function testGetFlashesReturnsActiveFlashes(): void
    {
        $collector = $this->makeCollector(['session' => ['class' => Session::class]]);

        Yii::$app->session->open();
        Yii::$app->session->setFlash('success', 'Saved.');
        Yii::$app->session->setFlash('warning', 'Heads up.');

        $flashes = $this->invoke(
            $collector,
            'getFlashes',
        );

        self::assertIsArray(
            $flashes,
            'Flashes must be an array.',
        );
        self::assertSame(
            'Saved.',
            $flashes['success'] ?? null,
            "Flash 'success' must round-trip.",
        );
        self::assertSame(
            'Heads up.',
            $flashes['warning'] ?? null,
            "Flash 'warning' must round-trip.",
        );

        Yii::$app->session->close();
    }

    public function testGetFlashesReturnsEmptyWhenCountersAreNotArray(): void
    {
        $collector = $this->makeCollector(['session' => ['class' => Session::class]]);

        $session = Yii::$app->session;

        $session->open();
        $session->set($session->flashParam, 'not-an-array');

        self::assertSame(
            [],
            $this->invoke(
                $collector,
                'getFlashes',
            ),
            "Non-array counters must collapse to '[]'.",
        );

        $session->close();
    }

    public function testGetFlashesReturnsEmptyWhenSessionIsInactive(): void
    {
        $collector = $this->makeCollector(['session' => ['class' => Session::class]]);

        Yii::$app->session->close();

        self::assertSame(
            [],
            $this->invoke(
                $collector,
                'getFlashes',
            ),
            "Inactive session must yield '[]'.",
        );
    }

    public function testGetFlashesReturnsEmptyWhenSessionIsNotConfigured(): void
    {
        $collector = $this->makeCollector();

        self::assertSame(
            [],
            $this->invoke(
                $collector,
                'getFlashes',
            ),
            "Missing session component must yield '[]'.",
        );
    }

    public function testIdPairsWithTheRequestPanel(): void
    {
        self::assertSame(
            'request',
            (new RequestCollector())->id(),
            "Stable ID must be 'request'.",
        );
    }

    public function testNormalizeGlobalValueCollapsesEmptyValuesToEmptyArray(): void
    {
        $collector = $this->makeCollector();

        foreach ([null, false, '', [], 0, '0'] as $empty) {
            self::assertSame(
                [],
                $this->invoke(
                    $collector,
                    'normalizeGlobalValue',
                    [$empty],
                ),
                "Empty value must collapse to '[]'.",
            );
        }
    }

    public function testNormalizeGlobalValuePassesThroughNonEmptyValues(): void
    {
        $collector = $this->makeCollector();

        self::assertSame(
            ['a' => 1],
            $this->invoke(
                $collector,
                'normalizeGlobalValue',
                [['a' => 1]],
            ),
            'Non-empty arrays must round-trip unchanged.',
        );
        self::assertSame(
            'value',
            $this->invoke(
                $collector,
                'normalizeGlobalValue',
                ['value'],
            ),
            'Non-empty strings must round-trip unchanged.',
        );
        self::assertSame(
            42,
            $this->invoke(
                $collector,
                'normalizeGlobalValue',
                [42],
            ),
            'Non-zero numbers must round-trip unchanged.',
        );
    }

    public function testNormalizeResponseHeadersAggregatesDuplicates(): void
    {
        $collector = $this->makeCollector();

        $headers = $this->invoke(
            $collector,
            'normalizeResponseHeaders',
            [['X-Foo: a', 'X-Foo: b', 'X-Foo: c']],
        );

        self::assertSame(
            ['X-Foo' => ['a', 'b', 'c']],
            $headers,
            'Duplicate names must aggregate into a list.',
        );
    }

    public function testNormalizeResponseHeadersAppendsToExistingArray(): void
    {
        $collector = $this->makeCollector();

        $headers = $this->invoke(
            $collector,
            'normalizeResponseHeaders',
            [['X-Foo: a', 'X-Foo: b', 'X-Foo: c', 'X-Foo: d']],
        );

        self::assertSame(
            ['X-Foo' => ['a', 'b', 'c', 'd']],
            $headers,
            'Third+ duplicate values must append to the existing list.',
        );
    }

    public function testNormalizeResponseHeadersMasksCensoredHeader(): void
    {
        $collector = $this->makeCollector();

        $collector->censoredVariableNames = ['X-Secret'];

        $headers = $this->invoke(
            $collector,
            'normalizeResponseHeaders',
            [['Content-Type: application/json', 'X-Secret: sensitive']],
        );

        self::assertIsArray(
            $headers,
            'Header map must be an array.',
        );
        self::assertSame(
            'application/json',
            $headers['Content-Type'] ?? null,
            'Non-censored value must round-trip verbatim.',
        );
        self::assertSame(
            '****',
            $headers['X-Secret'] ?? null,
            'Censored header value must be masked.',
        );
    }

    public function testNormalizeResponseHeadersPreservesMalformedLinesAtIntegerKeys(): void
    {
        $collector = $this->makeCollector();

        $headers = $this->invoke(
            $collector,
            'normalizeResponseHeaders',
            [['HTTP/1.1 200 OK', 'X-Foo: bar']],
        );

        self::assertIsArray(
            $headers,
            "'Header map' must be an array.",
        );
        self::assertSame(
            'HTTP/1.1 200 OK',
            $headers[0] ?? null,
            'Bare line without colon must land at an int-keyed slot.',
        );
        self::assertSame(
            'bar',
            $headers['X-Foo'] ?? null,
            'Well-formed line must land at the named slot.',
        );
    }

    public function testNormalizeResponseHeadersReturnsEmptyArrayForEmptyInput(): void
    {
        $collector = $this->makeCollector();

        self::assertSame(
            [],
            $this->invoke($collector, 'normalizeResponseHeaders', [[]]),
            "Empty input must yield '[]'.",
        );
    }

    public function testNormalizeTopLevelDataDropsIntKeyedEntries(): void
    {
        $collector = $this->makeCollector();

        self::assertSame(
            ['route' => 'site/index', 'statusCode' => 200],
            $this->invoke(
                $collector,
                'normalizeTopLevelData',
                [['route' => 'site/index', 0 => 'drop-me', 'statusCode' => 200]],
            ),
            'Int-keyed entries must be dropped.',
        );
    }

    /**
     * Extracts the captured payload, failing when the started collector produces no snapshot.
     *
     * @param RequestCollector $collector Started collector.
     *
     * @return array<array-key, mixed> Captured payload.
     */
    private function captureData(RequestCollector $collector): array
    {
        $snapshot = $collector->capture();

        self::assertNotNull($snapshot, 'Started collector must capture a snapshot.');

        return $snapshot->data();
    }

    /**
     * Creates a started collector on top of a mocked web application.
     *
     * @param array<string, mixed> $components Extra application components.
     *
     * @return RequestCollector Started collector.
     */
    private function makeCollector(array $components = []): RequestCollector
    {
        $this->mockWebApplication(['components' => $components]);

        $collector = new RequestCollector();

        $collector->startup();

        return $collector;
    }
}
