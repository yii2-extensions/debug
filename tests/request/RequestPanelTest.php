<?php

declare(strict_types=1);

namespace yii\debug\tests\request;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\base\{Action, InlineAction};
use yii\debug\panels\RequestPanel;
use yii\debug\tests\support\TestCase;
use yii\web\{Controller, Session};

/**
 * Unit tests for {@see RequestPanel} covering header capture, action narrowing, censor masking, response-header
 * aggregation, flash retrieval, the toolbar status chip, and the saved-payload narrowing.
 */
#[Group('panel')]
#[Group('request')]
final class RequestPanelTest extends TestCase
{
    public function testCensorArrayLeavesUnmatchedKeysUntouched(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $panel->censoredVariableNames = ['Authorization'];

        $masked = $this->invoke(
            $panel,
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
        $panel = $this->makePanel(RequestPanel::class);

        $panel->censoredVariableNames = ['POST'];

        $masked = $this->invoke(
            $panel,
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
        $panel = $this->makePanel(RequestPanel::class);

        $panel->censoredVariableNames = ['requestBody.Decoded'];

        $masked = $this->invoke(
            $panel,
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
        $panel = $this->makePanel(RequestPanel::class);

        $panel->censoredVariableNames = ['Authorization'];

        self::assertSame(
            [],
            $this->invoke(
                $panel,
                'censorArray',
                [[]],
            ),
            "Empty data must short-circuit to '[]'.",
        );
    }

    public function testCensorArrayReturnsEarlyWhenNoCensorList(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $masked = $this->invoke(
            $panel,
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

    public function testGetDetailRendersWithCapturedData(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $panel->data = [
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
        ];

        self::assertNotEmpty(
            $panel->getDetail(),
            'Detail view must produce markup.',
        );
    }

    public function testGetDetailUsesEmptySummaryWhenControllerIsNotDefaultController(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        Yii::$app->controller = new Controller('plain', Yii::$app);

        $panel->data = [
            'route' => 'site/index',
            'statusCode' => 200,
            'general' => ['method' => 'GET'],
            'requestHeaders' => [],
            'responseHeaders' => [],
        ];

        self::assertNotEmpty(
            $panel->getDetail(),
            'Non-default controller must fall back to an empty summary.',
        );
    }

    public function testGetFlashesReturnsActiveFlashes(): void
    {
        $panel = $this->makePanel(
            RequestPanel::class,
            ['session' => ['class' => Session::class]],
        );

        Yii::$app->session->open();
        Yii::$app->session->setFlash('success', 'Saved.');
        Yii::$app->session->setFlash('warning', 'Heads up.');

        $flashes = $this->invoke(
            $panel,
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
        $panel = $this->makePanel(
            RequestPanel::class,
            ['session' => ['class' => Session::class]],
        );

        $session = Yii::$app->session;

        $session->open();
        $session->set($session->flashParam, 'not-an-array');

        self::assertSame(
            [],
            $this->invoke(
                $panel,
                'getFlashes',
            ),
            "Non-array counters must collapse to '[]'.",
        );

        $session->close();
    }

    public function testGetFlashesReturnsEmptyWhenSessionIsInactive(): void
    {
        $panel = $this->makePanel(
            RequestPanel::class,
            ['session' => ['class' => Session::class]],
        );

        Yii::$app->session->close();

        self::assertSame(
            [],
            $this->invoke(
                $panel,
                'getFlashes',
            ),
            "Inactive session must yield '[]'.",
        );
    }

    public function testGetFlashesReturnsEmptyWhenSessionIsNotConfigured(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        self::assertSame(
            [],
            $this->invoke(
                $panel,
                'getFlashes',
            ),
            "Missing session component must yield '[]'.",
        );
    }

    public function testGetNameAndIcon(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

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

    public function testGetStatusCodeCoercesNumericStringStatusCode(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $panel->data = ['statusCode' => '404'];

        self::assertSame(
            404,
            $this->invoke(
                $panel,
                'getStatusCode',
            ),
            "Numeric-string status must be coerced to 'int'.",
        );
    }

    public function testGetStatusCodeFallsBackTo200ForNonArrayData(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $this->setInaccessibleProperty(
            $panel,
            'data',
            'corrupt',
        );

        self::assertSame(
            200,
            $this->invoke(
                $panel,
                'getStatusCode',
            ),
            "Non-array data must default to '200'.",
        );
    }

    public function testGetStatusCodeFallsBackTo200ForNonNumericStatusCode(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $panel->data = ['statusCode' => 'not-a-number'];

        self::assertSame(
            200,
            $this->invoke(
                $panel,
                'getStatusCode',
            ),
            "Non-numeric status must default to '200'.",
        );
    }

    public function testGetStatusCodeReturnsIntStatusCode(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $panel->data = ['statusCode' => 500];

        self::assertSame(
            500,
            $this->invoke(
                $panel,
                'getStatusCode',
            ),
            'Int status must be returned verbatim.',
        );
    }

    public function testGetSummaryRendersChip(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $panel->data = ['statusCode' => 200];

        self::assertNotEmpty(
            $panel->getSummary(),
            'Summary view must produce markup.',
        );
    }

    public function testGetToolbarItemsRendersDangerForOtherStatus(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $panel->data = ['statusCode' => 500];

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
            'danger',
            $first['status'] ?? null,
            'Server errors must carry the danger status.',
        );
        self::assertSame(
            500,
            $first['value'] ?? null,
            'Value must echo the captured status code.',
        );
    }

    public function testGetToolbarItemsRendersInfoForStatus3xx(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $panel->data = ['statusCode' => 302];

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
            'info',
            $first['status'] ?? null,
            'Redirects must carry the info status.',
        );
    }

    public function testGetToolbarItemsRendersSuccessForStatus2xx(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $panel->data = ['statusCode' => 201];

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
            'success',
            $first['status'] ?? null,
            '2xx must carry the success status.',
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

    public function testGetToolbarItemsTreatsUnknownStatusTextAsEmpty(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $panel->data = ['statusCode' => 299];

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
            'Status code: 299 ',
            $first['title'] ?? null,
            'Unknown status code must render with a blank trailing label.',
        );
    }

    public function testNormalizeGlobalValueCollapsesEmptyValuesToEmptyArray(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        foreach ([null, false, '', [], 0, '0'] as $empty) {
            self::assertSame(
                [],
                $this->invoke(
                    $panel,
                    'normalizeGlobalValue',
                    [$empty],
                ),
                "Empty value must collapse to '[]'.",
            );
        }
    }

    public function testNormalizeGlobalValuePassesThroughNonEmptyValues(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        self::assertSame(
            ['a' => 1],
            $this->invoke(
                $panel,
                'normalizeGlobalValue',
                [['a' => 1]],
            ),
            'Non-empty arrays must round-trip unchanged.',
        );
        self::assertSame(
            'value',
            $this->invoke(
                $panel,
                'normalizeGlobalValue',
                ['value'],
            ),
            'Non-empty strings must round-trip unchanged.',
        );
        self::assertSame(
            42,
            $this->invoke(
                $panel,
                'normalizeGlobalValue',
                [42],
            ),
            'Non-zero numbers must round-trip unchanged.',
        );
    }

    public function testNormalizeResponseHeadersAggregatesDuplicates(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $headers = $this->invoke(
            $panel,
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
        $panel = $this->makePanel(RequestPanel::class);

        $headers = $this->invoke(
            $panel,
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
        $panel = $this->makePanel(RequestPanel::class);

        $panel->censoredVariableNames = ['X-Secret'];

        $headers = $this->invoke(
            $panel,
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
        $panel = $this->makePanel(RequestPanel::class);

        $headers = $this->invoke(
            $panel,
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
        $panel = $this->makePanel(RequestPanel::class);

        self::assertSame(
            [],
            $this->invoke($panel, 'normalizeResponseHeaders', [[]]),
            "Empty input must yield '[]'.",
        );
    }

    public function testNormalizeTopLevelDataDropsIntKeyedEntries(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        self::assertSame(
            ['route' => 'site/index', 'statusCode' => 200],
            $this->invoke(
                $panel,
                'normalizeTopLevelData',
                [['route' => 'site/index', 0 => 'drop-me', 'statusCode' => 200]],
            ),
            'Int-keyed entries must be dropped.',
        );
    }

    public function testSaveBuildsActionFromInlineAction(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $controller = new Controller('site', Yii::$app);
        $action = new InlineAction('index', $controller, 'actionIndex');

        Yii::$app->requestedAction = $action;

        $saved = $panel->save();

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

    public function testSaveBuildsActionFromRegularAction(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $controller = new Controller('site', Yii::$app);

        $action = new class ('run', $controller) extends Action {
            public function run(): void {}
        };

        Yii::$app->requestedAction = $action;

        $saved = $panel->save();

        self::assertSame(
            $action::class . '::run()',
            $saved['action'] ?? null,
            "Regular action must format as 'ActionFQCN::run()'.",
        );
    }

    public function testSaveCapturesRequestBodyWhenNonEmpty(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $request = Yii::$app->getRequest();

        $request->setRawBody('{"k":"v"}');
        $request->setBodyParams(['k' => 'v']);
        $request->getHeaders()->set('Content-Type', 'application/json');

        $saved = $panel->save();

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

    public function testSaveCensorsRequestHeadersListedInCensoredVariableNames(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $panel->censoredVariableNames = ['authorization'];

        Yii::$app->getRequest()->getHeaders()->set('Authorization', 'Bearer secret');

        $saved = $panel->save();

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

    public function testSaveCollapsesSingleValueHeaderArrayToScalar(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        Yii::$app->getRequest()->getHeaders()->set('X-Single', 'only');

        $saved = $panel->save();

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

    public function testSaveKeepsMultiValueHeaderAsArray(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $request = Yii::$app->getRequest();

        $request->getHeaders()->add('X-Multi', 'a')->add('X-Multi', 'b');

        $saved = $panel->save();

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

    public function testSaveLeavesActionAsNullWhenNoRequestedAction(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        Yii::$app->requestedAction = null;
        Yii::$app->requestedRoute = 'site/default';

        $saved = $panel->save();

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

    public function testSaveLeavesRequestBodyEmptyWhenRawBodyIsEmpty(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        Yii::$app->getRequest()->setRawBody('');

        $saved = $panel->save();

        self::assertSame(
            [],
            $saved['requestBody'] ?? null,
            "Empty raw body must collapse to '[]'.",
        );
    }

    public function testSaveSurfacesConfiguredDisplayVars(): void
    {
        $panel = $this->makePanel(RequestPanel::class);

        $panel->displayVars = ['_GET'];
        $GLOBALS['_GET'] = ['q' => 'search-term'];

        $saved = $panel->save();

        self::assertSame(
            ['q' => 'search-term'],
            $saved['GET'] ?? null,
            'Configured displayVar must surface under its trimmed key.',
        );
    }
}
