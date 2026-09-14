<?php

declare(strict_types=1);

namespace yii\debug\tests;

use Override;
use PHPUnit\Framework\Attributes\Group;
use yii\debug\{Module, Panel, ToolbarDataMapper};
use yii\debug\tests\support\stub\MinimalToolbarPanel;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see ToolbarDataMapper} and its compatibility lane for custom Yii2 panel envelopes.
 */
#[Group('toolbar')]
final class ToolbarDataMapperTest extends TestCase
{
    public function testMapAppliesDefaultPositionAndHeight(): void
    {
        $this->mockWebApplication();

        $result = ToolbarDataMapper::create('capture-tag', 'Yii Debugger')
            ->withNavigation('/debug/index')
            ->map([]);

        self::assertSame(
            'bottom',
            $result['position'],
            "Position must default to 'bottom'.",
        );
        self::assertSame(
            50,
            $result['defaultHeight'],
            "Drawer height must default to '50'.",
        );
    }

    public function testMapNormalizesPortablePanelsWithoutDroppingExtensionFields(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug');

        $panel = new class extends Panel {
            #[Override]
            public function getName(): string
            {
                return 'Extended';
            }

            #[Override]
            public function getToolbarData(): array
            {
                return [
                    'extension' => 'panel-value',
                    'items' => [
                        [
                            'extension' => 'item-value',
                            'label' => 'Count',
                            'value' => 42,
                        ],
                    ],
                ];
            }
        };

        $panel->id = 'extended';
        $panel->module = $module;
        $panel->tag = 'capture-tag';

        $result = ToolbarDataMapper::create('capture-tag', 'Yii Debugger')
            ->withNavigation('/debug/index')
            ->map(['extended' => $panel]);

        self::assertSame(
            '/debug/index',
            $result['configUrl'],
            'Missing Config panel URL must fall back to the non-null history URL required by Debug Core.',
        );

        $items = $result['items'];
        $extendedPanel = $items[0] ?? null;

        self::assertIsArray(
            $extendedPanel,
            'Mapped toolbar payload must contain the normalized panel envelope.',
        );
        self::assertSame(
            'panel-value',
            $extendedPanel['extension'] ?? null,
            'Unknown panel fields must survive DTO normalization for custom integrations.',
        );

        $toolbarItems = $extendedPanel['items'] ?? null;

        self::assertIsArray(
            $toolbarItems,
            'Mapped toolbar panel must contain its normalized item list.',
        );

        $toolbarItem = $toolbarItems[0] ?? null;

        self::assertIsArray(
            $toolbarItem,
            'Mapped toolbar item must remain an array for the JavaScript compatibility boundary.',
        );
        self::assertSame(
            'item-value',
            $toolbarItem['extension'] ?? null,
            'Unknown toolbar-item fields must survive DTO normalization.',
        );
        self::assertSame(
            '42',
            $toolbarItem['value'] ?? null,
            'Portable DTO normalization must coerce scalar metric values to strings.',
        );
        self::assertSame(
            'default',
            $toolbarItem['status'] ?? null,
            'Portable DTO normalization must apply the shared default status.',
        );
    }

    public function testMapPreservesEnvelopeProvidedIdTitleAndUrl(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug');

        $panel = new class extends Panel {
            #[Override]
            public function getName(): string
            {
                return 'Ignored Name';
            }

            #[Override]
            public function getToolbarData(): array
            {
                return [
                    'id' => 7,
                    'title' => 'Custom Title',
                    'url' => '/custom-url',
                    'items' => [['value' => 1]],
                ];
            }
        };

        $panel->id = 'own';
        $panel->module = $module;
        $panel->tag = 'capture-tag';

        $result = ToolbarDataMapper::create('capture-tag', 'Yii Debugger')
            ->withNavigation('/debug/index')
            ->map(['own' => $panel]);

        $mapped = $result['items'][0] ?? null;

        self::assertIsArray(
            $mapped,
            'Mapped payload must contain the panel envelope.',
        );
        self::assertSame(
            '7',
            $mapped['id'] ?? null,
            'Envelope ID must win over the registry key and be coerced.',
        );
        self::assertSame(
            'Custom Title',
            $mapped['title'] ?? null,
            'Envelope title must win over the panel name.',
        );
        self::assertSame(
            '/custom-url',
            $mapped['url'] ?? null,
            'Envelope URL must win over the generated URL.',
        );
    }

    public function testMapProcessesTypedPanelAfterFreeFormEnvelope(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug');
        $freeFormPanel = new MinimalToolbarPanel();

        $freeFormPanel->id = 'free-form';
        $freeFormPanel->module = $module;
        $freeFormPanel->tag = 'capture-tag';

        $typed = new class extends Panel {
            #[Override]
            public function getName(): string
            {
                return 'Typed';
            }

            #[Override]
            public function getToolbarData(): array
            {
                return ['items' => [['value' => 9]]];
            }
        };

        $typed->id = 'typed';
        $typed->module = $module;
        $typed->tag = 'capture-tag';

        $result = ToolbarDataMapper::create('capture-tag', 'Yii Debugger')
            ->withNavigation('/debug/index')
            ->map(['free-form' => $freeFormPanel, 'typed' => $typed]);

        self::assertCount(
            2,
            $result['items'],
            'Panels after a free-form envelope must still be processed.',
        );
        self::assertSame(
            'typed',
            $result['items'][1]['id'] ?? null,
            'Typed panel must follow the free-form envelope.',
        );
    }

    public function testMapRetainsFreeFormPanelEnvelope(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug');
        $panel = new MinimalToolbarPanel();

        $panel->id = 'free-form';
        $panel->module = $module;
        $panel->tag = 'capture-tag';

        $result = ToolbarDataMapper::create('capture-tag', 'Yii Debugger')
            ->withNavigation('/debug/index', '/debug/view?panel=config')
            ->map(['free-form' => $panel]);

        $freeFormPanel = $result['items'][0] ?? null;

        self::assertIsArray(
            $freeFormPanel,
            'Mapped toolbar payload must retain the free-form panel envelope.',
        );
        self::assertSame(
            'minimal',
            $freeFormPanel['chip'] ?? null,
            'A free-form custom panel must remain available through the compatibility lane.',
        );
        self::assertSame(
            'free-form',
            $freeFormPanel['id'] ?? null,
            'Historical panel ID defaults must still be injected.',
        );
        self::assertArrayHasKey(
            'title',
            $freeFormPanel,
            'Historical panel title defaults must still be injected.',
        );
        self::assertArrayHasKey(
            'url',
            $freeFormPanel,
            'Historical panel URL defaults must still be injected.',
        );
    }

    public function testMapSkipsInvisiblePanelsBeforeReadingToolbarData(): void
    {
        $this->mockWebApplication();

        $panel = new class extends Panel {
            public bool $toolbarDataRead = false;

            #[Override]
            public function getToolbarData(): array
            {
                $this->toolbarDataRead = true;

                return ['items' => [['value' => 'hidden']]];
            }

            #[Override]
            public function isVisible(): bool
            {
                return false;
            }
        };

        $result = ToolbarDataMapper::create('capture-tag', 'Yii Debugger')
            ->withNavigation('/debug/index')
            ->map(['hidden' => $panel]);

        self::assertFalse(
            $panel->toolbarDataRead,
            'An invisible panel must be rejected before its toolbar payload is requested.',
        );
        self::assertSame(
            [],
            $result['items'],
            'Invisible panels must not create toolbar entries.',
        );
    }

    public function testMergePanelExtensionsReturnsTypedEnvelopeWhenItemsAreNotArrays(): void
    {
        self::assertSame(
            [
                'extension' => 'preserved',
                'items' => [],
                'id' => 'typed',
            ],
            $this->invokeStatic(
                ToolbarDataMapper::class,
                'mergePanelExtensions',
                [
                    ['extension' => 'preserved', 'items' => 'free-form'],
                    ['items' => [], 'id' => 'typed'],
                ],
            ),
            'Non-array original items must leave the normalized item list unchanged.',
        );
    }

    public function testPanelRejectsInvalidPanelEnvelopes(): void
    {
        self::assertNull(
            $this->invokeStatic(
                ToolbarDataMapper::class,
                'panel',
                [['id' => 'invalid-item', 'title' => 'Invalid item', 'items' => ['not-an-array']]],
            ),
            'A non-array toolbar item cannot be normalized.',
        );
        self::assertNull(
            $this->invokeStatic(
                ToolbarDataMapper::class,
                'panel',
                [['id' => 'missing-value', 'title' => 'Missing value', 'items' => [[]]]],
            ),
            'A toolbar item without a coercible value cannot be normalized.',
        );
        self::assertNull(
            $this->invokeStatic(
                ToolbarDataMapper::class,
                'panel',
                [['id' => [], 'title' => 'Invalid ID', 'items' => []]],
            ),
            'A toolbar panel without a coercible ID cannot be normalized.',
        );
    }

    public function testWithBrandingAndPresentationApplyOptionalChrome(): void
    {
        $this->mockWebApplication();

        $result = ToolbarDataMapper::create('capture-tag', 'Yii Debugger')
            ->withNavigation('/debug/index', '/debug/view?panel=config', '/debug/php-info')
            ->withPresentation('top', 80, '/assets/svg/')
            ->withBranding('/assets/svg/yii.svg', 'data:image/svg+xml,fallback', '8.3.0', '22.0.0')
            ->map([]);

        self::assertSame(
            '/debug/view?panel=config',
            $result['configUrl'],
            'Explicit config URL must win.',
        );
        self::assertSame(
            '/debug/php-info',
            $result['phpInfoUrl'],
            'PHP info URL must reach the payload.',
        );
        self::assertSame(
            'top',
            $result['position'],
            'Position must be overridable.',
        );
        self::assertSame(
            80,
            $result['defaultHeight'],
            'Drawer height must be overridable.',
        );
        self::assertSame(
            '/assets/svg/',
            $result['iconBaseUrl'],
            'Icon base URL must reach the payload.',
        );
        self::assertSame(
            '/assets/svg/yii.svg',
            $result['logo'],
            'Primary logo must reach the payload.',
        );
        self::assertSame(
            'data:image/svg+xml,fallback',
            $result['logoFallback'],
            'Fallback logo must be retained.',
        );
        self::assertSame(
            '8.3.0',
            $result['phpVersion'],
            'PHP version label must reach the payload.',
        );
        self::assertSame(
            '22.0.0',
            $result['yiiVersion'],
            'Yii version label must reach the payload.',
        );
    }

    public function testWithersLeaveTheSourceMapperUntouched(): void
    {
        $this->mockWebApplication();

        $base = ToolbarDataMapper::create('capture-tag', 'Yii Debugger')->withNavigation('/debug/index');

        $derived = $base
            ->withPresentation('top', 80)
            ->withBranding('/assets/svg/yii.svg');

        self::assertNotSame(
            $base,
            $derived,
            'Each wither must return a copy.',
        );

        $result = $base->map([]);

        self::assertSame(
            'bottom',
            $result['position'],
            'Source must keep its default position.',
        );
        self::assertSame(
            50,
            $result['defaultHeight'],
            'Source must keep its default height.',
        );
        self::assertNull(
            $result['logo'],
            'Source must keep its logo unset.',
        );
    }

    protected function tearDown(): void
    {
        $this->destroyApplication();

        parent::tearDown();
    }
}
