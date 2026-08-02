<?php

declare(strict_types=1);

namespace yii\debug\tests\inertia;

use JsonSerializable;
use PHPUnit\Framework\Attributes\Group;
use stdClass;
use Yii;
use yii\base\{View, ViewEvent};
use yii\debug\panels\inertia\InertiaSnapshot;
use yii\debug\panels\InertiaPanel;
use yii\debug\tests\support\TestCase;
use yii\inertia\{Manager, Page};

/**
 * Unit tests for {@see InertiaPanel} covering the component-gated enablement, the per-capture sidebar activation,
 * the page capture from the response and the root-view render params, the `X-Inertia-*` header snapshot, the
 * shared-prop keys, and the detail/toolbar rendering.
 */
#[Group('panel')]
#[Group('inertia')]
final class InertiaPanelTest extends TestCase
{
    public function testCaptureCapturesPageFromResponseData(): void
    {
        $panel = $this->makePanel(InertiaPanel::class, ['inertia' => ['class' => Manager::class]]);

        Yii::$app->response->data = new Page('site/index', ['user' => ['id' => 1]], '/site/index', 'v1');

        $saved = $panel->capture()->data();
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
        $panel = $this->makePanel(InertiaPanel::class, ['inertia' => ['class' => Manager::class]]);

        $page = new Page('site/about', [], '/site/about', 'v2');

        Yii::$app->view->trigger(
            View::EVENT_BEFORE_RENDER,
            new ViewEvent(['params' => ['page' => $page], 'viewFile' => __FILE__]),
        );

        $saved = $panel->capture()->data();
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
        $panel = $this->makePanel(InertiaPanel::class, ['inertia' => ['class' => Manager::class]]);

        Yii::$app->request->headers->set('X-Inertia', 'true');
        Yii::$app->request->headers->set('X-Inertia-Partial-Data', 'user,notifications');
        Yii::$app->request->headers->set('X-Inertia-Partial-Component', 'site/index');

        $saved = $panel->capture()->data();

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

    public function testCaptureCapturesSharedPropKeys(): void
    {
        $panel = $this->makePanel(
            InertiaPanel::class,
            ['inertia' => ['class' => Manager::class, 'shared' => ['auth' => 1, 'appName' => 'demo']]],
        );

        $saved = $panel->capture()->data();

        self::assertSame(
            ['auth', 'appName'],
            $saved['sharedKeys'] ?? null,
            'Top-level shared keys must be captured.',
        );
    }

    public function testCaptureReturnsNullPageForNonInertiaResponse(): void
    {
        $panel = $this->makePanel(InertiaPanel::class, ['inertia' => ['class' => Manager::class]]);

        Yii::$app->response->data = ['plain' => true];

        $saved = $panel->capture()->data();

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

    public function testGetDetailMarksSharedAndPageProps(): void
    {
        $panel = $this->makePanel(InertiaPanel::class);

        $this->hydratePanel($panel, InertiaSnapshot::capture(null, [
            'component' => 'site/index',
            'props' => ['auth' => ['isGuest' => true], 'post' => ['id' => 7]],
            'url' => '/site/index',
            'version' => 'v1',
        ], [], ['auth'], 200));

        $html = $panel->getDetail();

        self::assertStringContainsString(
            '>shared</span>',
            $html,
            "Config-shared props must wear the 'shared' badge.",
        );
        self::assertStringContainsString(
            '>page</span>',
            $html,
            "Page-specific props must wear the 'page' badge.",
        );
    }

    public function testGetDetailRendersComponentAndProps(): void
    {
        $panel = $this->makePanel(InertiaPanel::class);

        $this->hydratePanel($panel, InertiaSnapshot::capture(null, [
            'component' => 'site/index',
            'props' => ['user' => ['id' => 1]],
            'url' => '/site/index',
            'version' => 'v1',
        ], ['X-Inertia' => 'true'], [], 200));

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'site/index',
            $html,
            'Component name must surface in the detail.',
        );
        self::assertStringContainsString(
            'Props',
            $html,
            'Props section heading must be present.',
        );
        self::assertStringContainsString(
            '"user"',
            $html,
            'Prop keys must surface in the JSON block.',
        );
        self::assertStringContainsString(
            'Inertia visit',
            $html,
            "XHR captures must read as 'Inertia visit'.",
        );
    }

    public function testGetDetailRendersEmptyStateWhenPageMissing(): void
    {
        $panel = $this->makePanel(InertiaPanel::class);

        $this->hydratePanel($panel, InertiaSnapshot::capture(null, null, [], [], 200));

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'yii-debug-empty-state',
            $html,
            'Missing page must render the empty-state card.',
        );
        self::assertStringContainsString(
            'No Inertia page in this request',
            $html,
            'Card headline must describe the missing page.',
        );
    }

    public function testGetDetailRendersMessageWhenPageHasNoProps(): void
    {
        $panel = $this->makePanel(InertiaPanel::class);

        $this->hydratePanel($panel, InertiaSnapshot::capture(null, ['component' => 'site/index', 'props' => [], 'url' => '/', 'version' => 'v1'], [], [], 200));

        self::assertStringContainsString(
            'The page rendered without props.',
            $panel->getDetail(),
            'An empty page payload must render the no-props message.',
        );
    }

    public function testGetDetailRendersScalarPropTypesHeadersAndLongPayload(): void
    {
        $panel = $this->makePanel(InertiaPanel::class);

        $this->hydratePanel($panel, InertiaSnapshot::capture(null, [
            'component' => 'site/index',
            'props' => [
                'string' => str_repeat('a', 700),
                'integer' => 7,
                'float' => 1.5,
                'boolean' => true,
                'null' => null,
            ],
            'url' => '/site/index',
            'version' => 'v1',
        ], [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Data' => 'string,integer',
        ], [], 200));

        $html = $panel->getDetail();

        self::assertStringContainsString('Partial reload', $html, 'Partial request headers must classify the visit.');
        self::assertStringContainsString('X-Inertia-Partial-Data', $html, 'Negotiation headers must be listed.');
        self::assertStringContainsString('string(700)', $html, 'String props must include their length.');
        self::assertMatchesRegularExpression('/>\s*int\s*</', $html, 'Integer props must expose their type.');
        self::assertMatchesRegularExpression('/>\s*float\s*</', $html, 'Float props must expose their type.');
        self::assertMatchesRegularExpression('/>\s*bool\s*</', $html, 'Boolean props must expose their type.');
        self::assertMatchesRegularExpression('/>\s*null\s*</', $html, 'Null props must expose their type.');
        self::assertStringContainsString('yii-debug-cell-more', $html, 'Long raw payloads must use the expandable wrapper.');
    }

    public function testGetDetailRendersVersionConflictWhenStatusIs409(): void
    {
        $panel = $this->makePanel(InertiaPanel::class);

        $this->hydratePanel($panel, InertiaSnapshot::capture('http://example.test/site/index', null, ['X-Inertia' => 'true', 'X-Inertia-Version' => 'stale'], [], 409));

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'Version conflict interrupted this visit',
            $html,
            'Conflict headline must surface.',
        );
        self::assertStringContainsString(
            'http://example.test/site/index',
            $html,
            'Reload target must echo the `X-Inertia-Location` header.',
        );
    }

    public function testGetNameAndIcon(): void
    {
        $panel = $this->makePanel(InertiaPanel::class);

        self::assertSame(
            'Inertia',
            $panel->getName(),
            "Display name must be 'Inertia'.",
        );
        self::assertSame(
            'inertia',
            $panel->getToolbarIcon(),
            "Icon key must be 'inertia'.",
        );
    }

    public function testGetToolbarItemsCarryComponentName(): void
    {
        $panel = $this->makePanel(InertiaPanel::class);

        $this->hydratePanel($panel, InertiaSnapshot::capture(null, [
            'component' => 'site/index',
            'props' => [],
            'url' => '/site/index',
            'version' => 'v1',
        ], [], [], 200));

        $items = $this->invoke($panel, 'getToolbarItems');

        self::assertSame(
            [
                [
                    'title' => 'Inertia component',
                    'value' => 'site/index',
                ],
            ],
            $items,
            'Chip must carry the component name.',
        );
    }

    public function testGetToolbarItemsReturnEmptyListWithoutCapturedPage(): void
    {
        $panel = $this->makePanel(InertiaPanel::class);

        $this->hydratePanel($panel, InertiaSnapshot::capture(null, null, [], [], 200));

        self::assertSame(
            [],
            $this->invoke($panel, 'getToolbarItems'),
            'Missing page must skip the toolbar chip.',
        );
    }

    public function testHasContentReturnsFalseForPlainCapture(): void
    {
        $panel = $this->makePanel(InertiaPanel::class);

        $this->hydratePanel($panel, InertiaSnapshot::capture(null, null, [], [], 200));

        self::assertFalse(
            $panel->hasContent(),
            'Plain captures must hide the sidebar entry.',
        );
    }

    public function testHasContentReturnsTrueForCapturedPage(): void
    {
        $panel = $this->makePanel(InertiaPanel::class);

        $this->hydratePanel($panel, InertiaSnapshot::capture(null, ['component' => 'site/index', 'props' => [], 'url' => '/', 'version' => 'v1'], [], [], 200));

        self::assertTrue(
            $panel->hasContent(),
            'Captured page must surface the sidebar entry.',
        );
    }

    public function testHasContentReturnsTrueForInertiaXhrWithoutPage(): void
    {
        $panel = $this->makePanel(InertiaPanel::class);

        $this->hydratePanel($panel, InertiaSnapshot::capture('http://example.test/', null, ['X-Inertia' => 'true', 'X-Inertia-Version' => 'stale'], [], 409));

        self::assertTrue(
            $panel->hasContent(),
            'Version-conflict XHR must surface the sidebar entry.',
        );
    }

    public function testIsEnabledReturnsFalseWhenInertiaComponentCannotBeCreated(): void
    {
        $panel = $this->makePanel(InertiaPanel::class, ['inertia' => ['class' => 'missing\\InertiaManager']]);

        self::assertFalse(
            $panel->isEnabled(),
            'An invalid Inertia component definition must disable the panel.',
        );
    }

    public function testIsEnabledReturnsFalseWithoutInertiaComponent(): void
    {
        $panel = $this->makePanel(InertiaPanel::class);

        self::assertFalse(
            $panel->isEnabled(),
            'Missing `inertia` component must disable the panel.',
        );
    }

    public function testIsEnabledReturnsTrueWhenManagerIsRegistered(): void
    {
        $panel = $this->makePanel(InertiaPanel::class, ['inertia' => ['class' => Manager::class]]);

        self::assertTrue(
            $panel->isEnabled(),
            'Registered manager must enable the panel.',
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
            $this->invokeStatic(InertiaPanel::class, 'normalizePage', [$invalidJson]),
            'A page that cannot be JSON encoded must normalize to null.',
        );
        self::assertNull(
            $this->invokeStatic(InertiaPanel::class, 'normalizePage', [$scalar]),
            'A scalar JSON payload must normalize to null.',
        );
    }

    public function testSharedKeysReturnsEmptyListForMissingAndNonManagerComponents(): void
    {
        $this->makePanel(InertiaPanel::class);

        self::assertSame(
            [],
            $this->invokeStatic(InertiaPanel::class, 'sharedKeys'),
            'A missing manager must yield no shared keys.',
        );

        Yii::$app->set('inertia', new stdClass());

        self::assertSame(
            [],
            $this->invokeStatic(InertiaPanel::class, 'sharedKeys'),
            'A non-manager component must yield no shared keys.',
        );
    }
}
