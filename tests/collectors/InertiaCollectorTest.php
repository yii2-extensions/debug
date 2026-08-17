<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use JsonSerializable;
use PHPForge\Debug\Panel\Inertia\InertiaSnapshot;
use PHPUnit\Framework\Attributes\Group;
use stdClass;
use Yii;
use yii\base\{View, ViewEvent};
use yii\debug\collectors\InertiaCollector;
use yii\debug\tests\support\TestCase;
use yii\inertia\{Manager, Page};

/**
 * Unit tests for {@see InertiaCollector} covering the manager-gated capture, the page capture from the response and
 * the root-view render params, the `X-Inertia-*` header snapshot, the shared-prop keys, and the lifecycle.
 */
#[Group('collector')]
#[Group('inertia')]
final class InertiaCollectorTest extends TestCase
{
    public function testCaptureCapturesPageFromResponseData(): void
    {
        $collector = $this->makeCollector(['inertia' => ['class' => Manager::class]]);

        Yii::$app->response->data = new Page('site/index', ['user' => ['id' => 1]], '/site/index', 'v1');

        $saved = $this->captureData($collector);
        $page = $saved['page'] ?? null;

        self::assertIsArray($page);

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

        self::assertIsArray($capturedPage);

        self::assertSame(
            'site/about',
            $capturedPage['component'] ?? null,
            'Component must come from the render params.',
        );
    }

    public function testCaptureCapturesPartialReloadHeaders(): void
    {
        $collector = $this->makeCollector(['inertia' => ['class' => Manager::class]]);

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
        $collector = $this->makeCollector(['inertia' => ['class' => Manager::class]]);

        Yii::$app->response->headers->set('X-Inertia-Location', 'https://example.test/users');

        self::assertSame(
            'https://example.test/users',
            $this->captureSnapshot($collector)->location,
            'The external redirect target must be retained verbatim.',
        );
    }

    public function testCaptureCapturesSharedPropKeys(): void
    {
        $collector = $this->makeCollector(['inertia' => ['class' => Manager::class, 'shared' => ['auth' => 1, 'appName' => 'demo']]]);

        $saved = $this->captureData($collector);

        self::assertSame(
            ['auth', 'appName'],
            $saved['sharedKeys'] ?? null,
            'Top-level shared keys must be captured.',
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
        $collector = $this->makeCollector(['inertia' => ['class' => Manager::class]]);

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
            $this->invokeStatic(InertiaCollector::class, 'normalizePage', [$invalidJson]),
            'A page that cannot be JSON encoded must normalize to null.',
        );
        self::assertNull(
            $this->invokeStatic(InertiaCollector::class, 'normalizePage', [$scalar]),
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
        $collector = $this->makeCollector(['inertia' => ['class' => Manager::class]]);

        Yii::$app->view->trigger(
            View::EVENT_BEFORE_RENDER,
            new ViewEvent(['params' => ['page' => new Page('site/index', [], '/site/index', 'v1')], 'viewFile' => __FILE__]),
        );

        $collector->shutdown();

        $collector->startup();

        $saved = $this->captureData($collector);

        self::assertNull(
            $saved['page'] ?? null,
            'A restarted collector must not retain the page captured before shutdown.',
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

        self::assertNotNull($snapshot, 'Started collector must capture a snapshot.');

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
