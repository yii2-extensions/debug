<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use JsonSerializable;
use PHPForge\Debug\Helper\SensitiveDataRedactor;
use PHPForge\Debug\Panel\Inertia\InertiaSnapshot;
use PHPForge\Inertia\Page;
use PHPUnit\Framework\Attributes\Group;
use stdClass;
use Yii;
use yii\base\{View, ViewEvent};
use yii\debug\collectors\InertiaCollector;
use yii\debug\Module;
use yii\debug\tests\support\TestCase;
use yii\inertia\Manager;

/**
 * Unit tests for {@see InertiaCollector} covering the manager-gated capture, the page capture from the response and the
 * root-view render params, the `X-Inertia-*` header snapshot, the shared-prop keys, and the lifecycle.
 */
#[Group('collector')]
#[Group('inertia')]
final class InertiaCollectorTest extends TestCase
{
    public function testCaptureCapturesPageFromResponseData(): void
    {
        $collector = $this->makeCollector(
            ['inertia' => ['class' => Manager::class]],
        );

        Yii::$app->response->data = new Page('site/index', ['user' => ['id' => 1]], '/site/index', 'v1');

        $saved = $this->captureData($collector);

        $page = $saved['page'] ?? null;

        self::assertIsArray(
            $page,
            'Captured page must be an array.',
        );
        self::assertSame(
            'site/index',
            $page['component'] ?? null,
            'Component must come from the response page object.',
        );
        self::assertSame(
            ['user' => ['id' => 1]],
            $page['props'] ?? null,
            'Props must round-trip through JSON intact.',
        );
        self::assertSame(
            'v1',
            $page['version'] ?? null,
            'Version must be preserved.',
        );
    }

    public function testCaptureCapturesPageFromRootViewRenderParams(): void
    {
        $collector = $this->makeCollector(['inertia' => ['class' => Manager::class]]);

        $page = new Page('site/about', [], '/site/about', 'v2');

        Yii::$app->view->trigger(
            View::EVENT_BEFORE_RENDER,
            new ViewEvent(['params' => ['page' => $page], 'viewFile' => __FILE__]),
        );

        $saved = $this->captureData($collector);

        $capturedPage = $saved['page'] ?? null;

        self::assertIsArray(
            $capturedPage,
            'Captured page must be an array.',
        );
        self::assertSame(
            'site/about',
            $capturedPage['component'] ?? null,
            'Component must come from the render params.',
        );
    }

    public function testCaptureCapturesPartialReloadHeaders(): void
    {
        $collector = $this->makeCollector(
            ['inertia' => ['class' => Manager::class]],
        );

        Yii::$app->request->headers->set('X-Inertia', 'true');
        Yii::$app->request->headers->set('X-Inertia-Partial-Data', 'user,notifications');
        Yii::$app->request->headers->set('X-Inertia-Partial-Component', 'site/index');

        $saved = $this->captureData($collector);

        self::assertSame(
            [
                'X-Inertia' => 'true',
                'X-Inertia-Partial-Component' => 'site/index',
                'X-Inertia-Partial-Data' => 'user,notifications',
            ],
            $saved['requestHeaders'] ?? null,
            'Negotiation headers must be captured in display order.',
        );
    }

    public function testCaptureCapturesResponseLocationHeader(): void
    {
        $collector = $this->makeCollector(
            ['inertia' => ['class' => Manager::class]],
        );

        Yii::$app->response->headers->set('X-Inertia-Location', 'https://example.test/users');

        self::assertSame(
            'https://example.test/users',
            $this->captureSnapshot($collector)->location,
            'The external redirect target must be retained verbatim.',
        );
    }

    public function testCaptureCapturesSharedPropKeys(): void
    {
        $collector = $this->makeCollector(
            [
                'inertia' => [
                    'class' => Manager::class,
                    'shared' => [
                        'auth' => 1,
                        'appName' => 'demo',
                    ],
                ],
            ],
        );

        $saved = $this->captureData($collector);

        self::assertSame(
            ['auth', 'appName'],
            $saved['sharedKeys'] ?? null,
            'Top-level shared keys must be captured.',
        );
    }

    public function testCaptureRedactsConfiguredSensitiveDataFromLegacyPageAndLocation(): void
    {
        $collector = $this->makeCollector(
            ['inertia' => ['class' => Manager::class]],
        );
        $module = new Module('debug');

        $module->sensitiveKeys = [...SensitiveDataRedactor::DEFAULT_KEYS, 'tenant_signing_key'];
        $module->sensitiveKeyPrefixes = ['internal_'];
        $module->sensitiveKeyPatterns = [
            ...SensitiveDataRedactor::DEFAULT_PATTERNS,
            '~(?:^|_)vault(?:$|_)~i',
        ];
        $collector->module = $module;

        Yii::$app->response->data = new \yii\inertia\Page(
            'legacy/secrets',
            [
                'nested' => [
                    'tenant_signing_key' => 'exact-secret',
                    'internal_note' => 'prefix-secret',
                    'team_vault_key' => 'pattern-secret',
                    'passwordless_mode' => 'safe-passwordless',
                ],
            ],
            '/legacy?tenant_signing_key=page-exact&internal_note=page-prefix&team_vault_key=page-pattern&safe=visible',
            'legacy-v2',
        );
        Yii::$app->response->headers->set(
            'X-Inertia-Location',
            '/next?tenant_signing_key=location-exact&internal_note=location-prefix&team_vault_key=location-pattern&safe=visible',
        );

        $snapshot = $this->captureSnapshot($collector);
        $page = $snapshot->data()['page'] ?? null;

        self::assertIsArray($page, 'A legacy Inertia page must remain available after redaction.');
        $props = $page['props'] ?? null;

        self::assertIsArray($props, 'A legacy Inertia page must retain its props object after redaction.');
        self::assertSame(
            [
                'tenant_signing_key' => SensitiveDataRedactor::PLACEHOLDER,
                'internal_note' => SensitiveDataRedactor::PLACEHOLDER,
                'team_vault_key' => SensitiveDataRedactor::PLACEHOLDER,
                'passwordless_mode' => 'safe-passwordless',
            ],
            $props['nested'] ?? null,
            'Exact, prefix, and PCRE rules must redact nested legacy page props without false positives.',
        );
        self::assertSame(
            '/legacy?tenant_signing_key=%5Bredacted%5D&internal_note=%5Bredacted%5D&team_vault_key=%5Bredacted%5D&safe=visible',
            $page['url'] ?? null,
            'Configured rules must redact sensitive query values in the legacy page URL.',
        );
        self::assertSame(
            '/next?tenant_signing_key=%5Bredacted%5D&internal_note=%5Bredacted%5D&team_vault_key=%5Bredacted%5D&safe=visible',
            $snapshot->location,
            'Configured rules must redact sensitive query values in the Inertia location header.',
        );
    }

    public function testCaptureRedactsDefaultSensitiveDataFromCurrentPageRecursively(): void
    {
        $collector = $this->makeCollector(
            ['inertia' => ['class' => Manager::class]],
        );

        Yii::$app->response->data = new Page(
            'site/secrets',
            [
                'DB_PASSWORD' => 'database-secret',
                'nested' => [
                    'AWS_SECRET_ACCESS_KEY' => 'aws-secret',
                    'DATABASE_URL' => 'postgres://secret',
                    'DATABASE_HOST' => 'database.internal',
                    'tokenizer' => 'safe-tokenizer',
                ],
            ],
            '/site?token=page-secret&DATABASE_HOST=database.internal',
            'v3',
        );
        Yii::$app->response->headers->set(
            'X-Inertia-Location',
            '/next?DB_PASSWORD=location-secret&DATABASE_HOST=database.internal',
        );

        $snapshot = $this->captureSnapshot($collector);
        $page = $snapshot->data()['page'] ?? null;

        self::assertIsArray($page, 'A current Inertia page must remain available after redaction.');
        self::assertSame(
            [
                'DB_PASSWORD' => SensitiveDataRedactor::PLACEHOLDER,
                'nested' => [
                    'AWS_SECRET_ACCESS_KEY' => SensitiveDataRedactor::PLACEHOLDER,
                    'DATABASE_URL' => SensitiveDataRedactor::PLACEHOLDER,
                    'DATABASE_HOST' => 'database.internal',
                    'tokenizer' => 'safe-tokenizer',
                ],
            ],
            $page['props'] ?? null,
            'Default credential rules must redact current page props recursively while preserving safe lookalikes.',
        );
        self::assertSame(
            '/site?token=%5Bredacted%5D&DATABASE_HOST=database.internal',
            $page['url'] ?? null,
            'Default rules must redact the current page URL query.',
        );
        self::assertSame(
            '/next?DB_PASSWORD=%5Bredacted%5D&DATABASE_HOST=database.internal',
            $snapshot->location,
            'Default rules must redact the current Inertia location query.',
        );
    }

    public function testCaptureRetainsLegacyAdapterPageCompatibility(): void
    {
        $collector = $this->makeCollector(
            ['inertia' => ['class' => Manager::class]],
        );

        Yii::$app->response->data = new \yii\inertia\Page(
            'legacy/dashboard',
            ['legacy' => true],
            '/legacy',
            'legacy-v1',
        );

        $saved = $this->captureData($collector);

        $page = $saved['page'] ?? null;

        self::assertIsArray(
            $page,
            'Legacy adapter page must be normalized to a serializable array.',
        );

        self::assertSame(
            'legacy/dashboard',
            $page['component'] ?? null,
            'Existing applications returning the former adapter page DTO must continue to capture it.',
        );
    }

    public function testCaptureReturnsNullBeforeStartup(): void
    {
        $this->mockWebApplication();

        self::assertNull(
            (new InertiaCollector())->capture(),
            'Idle collector must record nothing.',
        );
    }

    public function testCaptureReturnsNullPageForNonInertiaResponse(): void
    {
        $collector = $this->makeCollector(
            ['inertia' => ['class' => Manager::class]],
        );

        Yii::$app->response->data = ['plain' => true];

        $saved = $this->captureData($collector);

        self::assertNull(
            $saved['page'] ?? null,
            'Non-Inertia response must yield a `null` page.',
        );
        self::assertSame(
            200,
            $saved['statusCode'] ?? null,
            'Status code must be captured.',
        );
    }

    public function testCaptureReturnsNullWithoutInertiaManagerComponent(): void
    {
        $collector = $this->makeCollector();

        self::assertNull(
            $collector->capture(),
            'A missing Inertia manager must collapse the capture to `null`.',
        );
    }

    public function testIdPairsWithTheInertiaPanel(): void
    {
        self::assertSame(
            'inertia',
            (new InertiaCollector())->id(),
            "Stable ID must be 'inertia'.",
        );
    }

    public function testNormalizePageReturnsNullForInvalidJsonAndScalarPayloads(): void
    {
        $collector = $this->makeCollector();
        $invalidJson = new class implements JsonSerializable {
            public function jsonSerialize(): string
            {
                return "\xB1\x31";
            }
        };
        $scalar = new class implements JsonSerializable {
            public function jsonSerialize(): string
            {
                return 'scalar';
            }
        };

        self::assertNull(
            $this->invoke($collector, 'normalizePage', [$invalidJson]),
            'A page that cannot be JSON encoded must normalize to null.',
        );
        self::assertNull(
            $this->invoke($collector, 'normalizePage', [$scalar]),
            'A scalar JSON payload must normalize to null.',
        );
    }

    public function testSharedKeysReturnsEmptyListForMissingAndNonManagerComponents(): void
    {
        $this->makeCollector();

        self::assertSame(
            [],
            $this->invokeStatic(InertiaCollector::class, 'sharedKeys'),
            'A missing manager must yield no shared keys.',
        );

        Yii::$app->set('inertia', new stdClass());

        self::assertSame(
            [],
            $this->invokeStatic(InertiaCollector::class, 'sharedKeys'),
            'A non-manager component must yield no shared keys.',
        );
    }

    public function testShutdownDetachesTheRenderListenerAndClearsThePage(): void
    {
        $collector = $this->makeCollector(
            ['inertia' => ['class' => Manager::class]],
        );

        Yii::$app->view->trigger(
            View::EVENT_BEFORE_RENDER,
            new ViewEvent(
                [
                    'params' => [
                        'page' => new Page('site/index', [], '/site/index', 'v1'),
                    ],
                    'viewFile' => __FILE__,
                ],
            ),
        );

        $collector->shutdown();

        Yii::$app->view->trigger(
            View::EVENT_BEFORE_RENDER,
            new ViewEvent(
                [
                    'params' => [
                        'page' => new Page('site/after', [], '/site/after', 'v2'),
                    ],
                    'viewFile' => __FILE__,
                ],
            ),
        );

        self::assertNull(
            $this->getInaccessibleProperty($collector, 'page'),
            'A stopped collector must detach its listener.',
        );
    }

    /**
     * Extracts the captured payload, failing when the started collector produces no snapshot.
     *
     * @param InertiaCollector $collector Started collector.
     *
     * @return array<array-key, mixed> Captured payload.
     */
    private function captureData(InertiaCollector $collector): array
    {
        return $this->captureSnapshot($collector)->data();
    }

    /**
     * Captures the Inertia snapshot, failing when the started collector produces nothing.
     *
     * @param InertiaCollector $collector Started collector.
     *
     * @return InertiaSnapshot Captured Inertia snapshot.
     */
    private function captureSnapshot(InertiaCollector $collector): InertiaSnapshot
    {
        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'Started collector must capture a snapshot.',
        );

        return $snapshot;
    }

    /**
     * Creates a started collector on top of a mocked web application.
     *
     * @param array<string, mixed> $components Extra application components.
     *
     * @return InertiaCollector Started collector.
     */
    private function makeCollector(array $components = []): InertiaCollector
    {
        $this->mockWebApplication(['components' => $components]);

        $collector = new InertiaCollector();

        $collector->startup();

        return $collector;
    }
}
