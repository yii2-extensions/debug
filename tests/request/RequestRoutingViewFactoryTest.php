<?php

declare(strict_types=1);

namespace yii\debug\tests\request;

use PHPForge\Debug\Panel\Request\{RequestDataNormalizer, RequestRenderer};
use PHPForge\Debug\Panel\Router\{CurrentRouteLogRow, RouterSnapshot};
use PHPForge\Debug\Storage\ExceptionSnapshot;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Yii;
use yii\base\Component;
use yii\base\Module as BaseModule;
use yii\debug\models\router\RouterRules;
use yii\debug\Module;
use yii\debug\panels\request\RequestRoutingViewFactory;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for the Yii2-to-core Request routing view adaptation.
 */
#[Group('panel')]
#[Group('request')]
final class RequestRoutingViewFactoryTest extends TestCase
{
    public function testBuildsCurrentRouteFromSnapshotAndMapsCompleteTrace(): void
    {
        $this->mockWebApplication();

        $routerRules = $this->routerRules(
            [
                [
                    'name' => 'unrelated',
                    'route' => 'fallback',
                    'suffix' => false,
                    'verb' => null,
                ],
                [
                    'mode' => 'parsing only',
                    'name' => 'orders/<action>',
                    'route' => 'orders/<action>',
                    'suffix' => '.json',
                    'type' => 'REST',
                    'verb' => ['get', 'GET', ' ', 'post'],
                ],
            ],
            prettyUrl: true,
            strictParsing: true,
            suffix: '.html',
        );

        $snapshot = new RouterSnapshot(
            action: 'app\\controllers\\OrderController::actionView()',
            route: 'orders/view',
            message: 'The matching rule was captured.',
            entries: [
                new CurrentRouteLogRow('unrelated', '', false),
                new CurrentRouteLogRow('GET orders/<action>', 'yii\\rest\\UrlRule', true),
            ],
        );

        $error = ExceptionSnapshot::fromThrowable(new RuntimeException('Captured router warning.'));

        $routing = RequestRoutingViewFactory::fromRequestData(
            ['actionParams' => ['id' => '42']],
            $snapshot,
            $error,
            $routerRules,
        );

        $inventory = $routing->inventory;

        $definition = $routing->current->getDefinition();

        self::assertNotNull(
            $inventory,
            'Yii2 Request routing must include its live route inventory.',
        );
        self::assertNotNull(
            $definition,
            'The matched trace rule must select a current-route definition.',
        );
        self::assertSame(
            'orders/view',
            $routing->current->getRoute(),
            'The Router snapshot must supply a missing route.',
        );
        self::assertSame(
            'app\\controllers\\OrderController::actionView()',
            $routing->current->getAction(),
            'The Router snapshot must supply a missing action.',
        );
        self::assertSame(
            ['id' => '42'],
            $routing->current->getParameters(),
            'Captured action parameters must be preserved.',
        );
        self::assertSame(
            'The matching rule was captured.',
            $routing->current->getMessage(),
            'The capture-time routing message must be preserved.',
        );
        self::assertSame(
            'Captured router warning.',
            $routing->current->getError(),
            'A hidden Router failure must be composed into Request.',
        );
        self::assertCount(
            2,
            $routing->current->getTrace(),
            'Every captured rule probe must be mapped.',
        );
        self::assertFalse(
            $routing->current->getTrace()[0]->matched,
            'An unsuccessful probe must remain unsuccessful.',
        );
        self::assertSame(
            'GET orders/<action>',
            $routing->current->getTrace()[1]->rule,
            'A successful probe must retain its rule descriptor.',
        );
        self::assertSame(
            'yii\\rest\\UrlRule',
            $routing->current->getTrace()[1]->parent,
            'A nested rule must retain its parent descriptor.',
        );
        self::assertTrue(
            $routing->current->getTrace()[1]->matched,
            'A successful probe must remain successful.',
        );
        self::assertSame(
            'orders/<action>',
            $definition->getPattern(),
            'The matched trace rule must identify a dynamic current-route definition.',
        );
        self::assertSame(
            ['GET', 'POST'],
            $definition->getMethods(),
            'Rule verbs must normalize and deduplicate.',
        );
        self::assertSame(
            '.json',
            $definition->getSuffix(),
            'Per-rule suffix metadata must remain available.',
        );
        self::assertSame(
            'parsing only',
            $definition->getMode(),
            'Rule mode metadata must remain available.',
        );
        self::assertSame(
            'REST',
            $definition->getType(),
            'Rule type metadata must remain available.',
        );
        self::assertSame(
            ['Pretty URL Enabled', 'Strict Parsing Enabled', 'Global Suffix: .html'],
            array_map(static fn($badge): string => $badge->label, $inventory->getBadges()),
            'The live URL-manager state must map to concise configuration badges.',
        );
        self::assertSame(
            ['success', 'success', 'warning'],
            array_map(static fn($badge): string => $badge->variant, $inventory->getBadges()),
            'Configuration badges must retain their semantic variants.',
        );
        self::assertSame(
            'Current URL manager configuration',
            $inventory->getSource(),
            'The live route source must be explicit.',
        );
        self::assertTrue(
            $inventory->isLive(),
            'The route inventory must be marked as live configuration.',
        );
    }

    public function testFallsBackToAnExactRouteTargetWithoutCapturedTrace(): void
    {
        $this->mockWebApplication();

        $routerRules = $this->routerRules(
            [
                ['name' => 'first', 'route' => 'first/index', 'verb' => []],
                ['name' => 'reports', 'route' => 'reports/index', 'verb' => []],
            ],
        );

        $snapshot = new RouterSnapshot(
            action: 'SnapshotAction',
            route: 'snapshot/route',
            message: null,
            entries: [],
        );

        $routing = RequestRoutingViewFactory::fromRequestData(
            [
                'action' => 'CapturedAction',
                'actionParams' => 'invalid',
                'route' => 'reports/index',
            ],
            $snapshot,
            null,
            $routerRules,
        );
        $inventory = $routing->inventory;

        self::assertNotNull(
            $inventory,
            'Yii2 Request routing must include its live route inventory.',
        );
        self::assertSame(
            'reports/index',
            $routing->current->getRoute(),
            'Request data must take precedence for the route.',
        );
        self::assertSame(
            'CapturedAction',
            $routing->current->getAction(),
            'Request data must take precedence for the action.',
        );
        self::assertSame(
            [],
            $routing->current->getParameters(),
            'Malformed action parameters must normalize to an empty map.',
        );
        self::assertSame(
            'reports',
            $routing->current->getDefinition()?->getPattern(),
            'An exact target must identify the current route when no resolution trace is available.',
        );
        self::assertSame(
            ['Pretty URL Disabled', 'Strict Parsing Disabled'],
            array_map(static fn($badge): string => $badge->label, $inventory->getBadges()),
            'An absent global suffix must not create an empty configuration badge.',
        );
    }

    public function testIgnoresEmptyPatternsAndUnmatchedTraceRows(): void
    {
        $this->mockWebApplication();

        $routerRules = $this->routerRules(
            [
                ['name' => '', 'route' => 'dynamic/<action>', 'verb' => []],
                ['name' => 'orders/<action>', 'route' => 'dynamic/<action>', 'verb' => []],
            ],
        );
        $snapshot = new RouterSnapshot(
            action: null,
            route: 'orders/view',
            message: null,
            entries: [
                new CurrentRouteLogRow('', '', true),
                new CurrentRouteLogRow('orders/<action>', '', false),
                new CurrentRouteLogRow('unrelated', '', true),
            ],
        );

        $routing = RequestRoutingViewFactory::fromRequestData([], $snapshot, null, $routerRules);

        self::assertNull(
            $routing->current->getDefinition(),
            'Only a successful, non-empty trace rule or exact target may select the current route definition.',
        );
    }
    public function testInventoryOmitsLoadedDebuggerModulesWithoutLoadingApplicationModules(): void
    {
        $this->mockWebApplication();

        Yii::$app->setModule('debug', self::createStub(Module::class));

        $admin = new BaseModule('admin', Yii::$app);

        $admin->setModule('diagnostics', self::createStub(Module::class));

        Yii::$app->setModule('admin', $admin);
        Yii::$app->setModule('help', ['class' => BaseModule::class]);

        $rules = $this->routerRules(
            [
                ['name' => 'debug', 'route' => 'debug'],
                ['name' => 'debug/<action>', 'route' => 'debug/<action>'],
                ['name' => 'monitor', 'route' => '/admin/diagnostics/view'],
                ['name' => 'debug/tutorial', 'route' => 'help/debug'],
                ['name' => 'debugging', 'route' => 'debugging/index'],
                ['name' => 'admin/reports', 'route' => 'admin/reports'],
                ['name' => 'custom-rule'],
            ],
        );

        $routing = RequestRoutingViewFactory::fromRequestData([], null, null, $rules);

        self::assertNotNull(
            $routing->inventory,
            'The application inventory must remain available.',
        );
        self::assertSame(
            ['debug/tutorial', 'debugging', 'admin/reports', 'custom-rule'],
            array_map(static fn($route): string => $route->getPattern(), $routing->inventory->getRoutes()),
            'Ownership, not a matching URL substring, must identify debugger routes.',
        );
        self::assertNull(
            Yii::$app->getModule('help', false),
            'Inventory inspection must not initialize application modules.',
        );
        self::assertCount(
            7,
            $rules->rules,
            'Original URL rules must remain unchanged.',
        );
    }

    public function testKeepsApplicationAndUnknownTraceRulesAlongsideRenamedDebuggerRules(): void
    {
        $this->mockWebApplication();

        $admin = new BaseModule('admin', Yii::$app);

        $admin->setModule('diagnostics', self::createStub(Module::class));

        Yii::$app->setModule('admin', $admin);

        $rules = $this->routerRules(
            [
                ['name' => 'monitor', 'route' => 'admin/diagnostics/view'],
                ['name' => 'debug/tutorial', 'route' => 'help/tutorial'],
            ],
        );

        $snapshot = new RouterSnapshot(
            action: '',
            route: 'help/tutorial',
            message: 'Application rule matched.',
            entries: [
                new CurrentRouteLogRow('GET,POST monitor', '', false),
                new CurrentRouteLogRow('historical-rule', '', false),
                new CurrentRouteLogRow('GET debug/tutorial', '', true),
            ],
        );
        $routing = RequestRoutingViewFactory::fromRequestData([], $snapshot, null, $rules);
        $html = RequestRenderer::render(RequestDataNormalizer::fromPanelData([], null), $routing);

        self::assertSame(
            ['historical-rule', 'GET debug/tutorial'],
            array_map(static fn($row): string => $row->rule, $routing->current->getTrace()),
            'Only known debugger-owned rule patterns must be omitted.',
        );
        self::assertSame(
            'Application rule matched.',
            $routing->current->getMessage(),
            'Application resolver context must remain visible.',
        );
        self::assertSame(
            'debug/tutorial',
            $routing->current->getDefinition()?->getPattern(),
            'The application match must remain identifiable.',
        );
        self::assertStringContainsString(
            'Routing resolution (2 rules tested)',
            $html,
            'The visible count must omit internal probes.',
        );
    }

    public function testKeepsResolverMessagesWhenNoRulesWereCaptured(): void
    {
        $this->mockWebApplication();

        Yii::$app->setModule('debug', self::createStub(Module::class));

        $rules = $this->routerRules([['name' => 'debug', 'route' => 'debug']]);

        $snapshot = new RouterSnapshot(action: '', route: '', message: 'Strict parsing rejected the request.', entries: []);

        $routing = RequestRoutingViewFactory::fromRequestData([], $snapshot, null, $rules);

        self::assertSame(
            'Strict parsing rejected the request.',
            $routing->current->getMessage(),
            'Message-only diagnostics must remain visible.',
        );
    }

    public function testKeepsTracePatternsSharedByApplicationAndDebuggerRules(): void
    {
        $this->mockWebApplication();

        Yii::$app->setModule('debug', self::createStub(Module::class));

        $rules = $this->routerRules(
            [
                ['name' => 'shared', 'route' => 'debug/view'],
                ['name' => 'shared', 'route' => 'reports/index'],
            ],
        );

        $snapshot = new RouterSnapshot(
            action: '',
            route: 'reports/index',
            message: null,
            entries: [new CurrentRouteLogRow('GET shared', '', true)],
        );

        $routing = RequestRoutingViewFactory::fromRequestData([], $snapshot, null, $rules);

        self::assertCount(
            1,
            $routing->current->getTrace(),
            'Ambiguous patterns must not erase application probes.',
        );
        self::assertSame(
            'reports/index',
            $routing->current->getDefinition()?->getTarget(),
            'Only the application definition must remain selectable.',
        );
    }

    public function testNormalizesScalarListAndMalformedVerbShapes(): void
    {
        $this->mockWebApplication();

        $routerRules = $this->routerRules(
            [
                ['name' => 'reports', 'route' => 'reports/index', 'verb' => 'get,get, POST | patch'],
                ['name' => 'jobs', 'route' => 'jobs/index', 'verb' => ['delete', 'delete', ' ', ' OPTIONS ', 42]],
                ['name' => 'health', 'route' => 'health/index', 'verb' => ['PUT' => 'PUT']],
                ['name' => 'status', 'route' => 'status/index', 'verb' => null],
                ['name' => 'empty', 'route' => 'empty/index', 'verb' => ''],
            ],
        );

        $routing = RequestRoutingViewFactory::fromRequestData([], null, null, $routerRules);

        $inventory = $routing->inventory;

        self::assertNotNull(
            $inventory,
            'Yii2 Request routing must include its live route inventory.',
        );

        $methods = [];

        foreach ($inventory->getRoutes() as $route) {
            $methods[] = $route->getMethods();
        }

        self::assertSame(
            [
                ['GET', 'POST', 'PATCH'],
                ['DELETE', 'OPTIONS'],
                [],
                [],
                [],
            ],
            $methods,
            'Every supported Yii2 verb shape must normalize without leaking invalid or duplicate values.',
        );
    }

    public function testOmitsDebuggerOnlyResolutionWithoutChangingCapturedDiagnostics(): void
    {
        $this->mockWebApplication();

        Yii::$app->setModule('debug', self::createStub(Module::class));

        $rules = $this->routerRules(
            [
                ['name' => 'debug', 'route' => 'debug'],
                ['name' => 'debug/<action>', 'route' => 'debug/<action>'],
            ],
        );

        $entries = [
            new CurrentRouteLogRow('debug', '', false),
            new CurrentRouteLogRow('debug/<action>', '', false),
        ];

        $message = 'No matching URL rules. Using default URL parsing logic.';

        $snapshot = new RouterSnapshot(action: '', route: 'site/index', message: $message, entries: $entries);

        $routing = RequestRoutingViewFactory::fromRequestData(
            [],
            $snapshot,
            ExceptionSnapshot::fromThrowable(new RuntimeException('Captured failure.')),
            $rules,
        );
        $html = RequestRenderer::render(RequestDataNormalizer::fromPanelData([], null), $routing);

        self::assertSame(
            [],
            $routing->current->getTrace(),
            'Internal rule probes must not reach Request.',
        );
        self::assertNull(
            $routing->current->getMessage(),
            'Debugger-only resolution messages must be omitted.',
        );
        self::assertStringNotContainsString(
            'Routing resolution',
            $html,
            'An internal-only trace must not render a disclosure.',
        );
        self::assertStringContainsString(
            'Captured failure.',
            $html,
            'Captured failures must remain visible.',
        );
        self::assertSame(
            $entries,
            $snapshot->entries(),
            'The original trace must remain intact.',
        );
        self::assertSame(
            $message,
            $snapshot->message,
            'The original resolver message must remain intact.',
        );
    }

    public function testRejectsMalformedStringMetadataWithoutCoercion(): void
    {
        $this->mockWebApplication();

        $routerRules = $this->routerRules(
            [
                [
                    'mode' => [],
                    'name' => 42,
                    'route' => false,
                    'suffix' => false,
                    'type' => new Component(),
                    'verb' => [],
                ],
            ],
        );

        $routing = RequestRoutingViewFactory::fromRequestData(
            ['action' => false, 'route' => 42],
            null,
            null,
            $routerRules,
        );
        $inventory = $routing->inventory;

        self::assertNotNull(
            $inventory,
            'Yii2 Request routing must include its live route inventory.',
        );
        self::assertSame(
            '',
            $routing->current->getRoute(),
            'A malformed route must not be string-coerced.',
        );
        self::assertNull(
            $routing->current->getAction(),
            'A malformed action must not be string-coerced.',
        );
        self::assertSame(
            [
                [
                    'pattern' => '',
                    'target' => null,
                    'suffix' => null,
                    'mode' => null,
                    'type' => null,
                ],
            ],
            array_map(
                static fn($route): array => [
                    'pattern' => $route->getPattern(),
                    'target' => $route->getTarget(),
                    'suffix' => $route->getSuffix(),
                    'mode' => $route->getMode(),
                    'type' => $route->getType(),
                ],
                $inventory->getRoutes(),
            ),
            'Malformed rule metadata must normalize without scalar or object coercion.',
        );
    }

    public function testReportsLiveInventoryFailuresWithoutDroppingCurrentRequestData(): void
    {
        $urlManager = new class extends Component {
            public bool $enablePrettyUrl = true;
            public bool $enableStrictParsing = false;
            public string|null $suffix = null;

            /**
             * @throws RuntimeException Always, to exercise URL-manager inspection failure handling.
             *
             * @return array<never, never>
             */
            public function getRules(): array
            {
                throw new RuntimeException('<script>route inventory failed</script>');
            }
        };

        $this->mockWebApplication(['components' => ['urlManager' => $urlManager]]);

        $routing = RequestRoutingViewFactory::fromRequestData(
            ['action' => 'SiteAction', 'route' => 'site/index'],
            null,
            null,
        );

        $inventory = $routing->inventory;

        self::assertNotNull(
            $inventory,
            'A failed live scan must still produce an inventory view.',
        );
        self::assertSame(
            'site/index',
            $routing->current->getRoute(),
            'Live scan failure must not erase the captured route.',
        );
        self::assertSame(
            'SiteAction',
            $routing->current->getAction(),
            'Live scan failure must not erase the captured action.',
        );
        self::assertSame(
            [],
            $inventory->getRoutes(),
            'A failed live scan must not invent route definitions.',
        );
        self::assertSame(
            'URL manager configuration could not be read: RuntimeException: <script>route inventory failed</script>',
            $inventory->getError(),
            'The live inventory failure must remain inspectable for the shared escaped renderer.',
        );
        self::assertSame(
            'Current URL manager configuration',
            $inventory->getSource(),
            'A failed live scan must retain its source context.',
        );
    }

    /**
     * @param list<array<string, mixed>> $rules
     */
    private function routerRules(
        array $rules,
        bool $prettyUrl = false,
        bool $strictParsing = false,
        string|null $suffix = null,
    ): RouterRules {
        $routerRules = new RouterRules();
        $routerRules->prettyUrl = $prettyUrl;
        $routerRules->strictParsing = $strictParsing;
        $routerRules->suffix = $suffix;
        $routerRules->rules = $rules;

        return $routerRules;
    }
}
