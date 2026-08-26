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

        $result = (new ToolbarDataMapper())->map(
            tag: 'capture-tag',
            title: 'Yii Debugger',
            indexUrl: '/debug/index',
            configUrl: null,
            panels: [],
        );

        self::assertSame('bottom', $result['position'], 'Position must default to `bottom`.');
        self::assertSame(50, $result['defaultHeight'], 'Drawer height must default to `50`.');
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

        $result = (new ToolbarDataMapper())->map(
            tag: 'capture-tag',
            title: 'Yii Debugger',
            indexUrl: '/debug/index',
            configUrl: null,
            panels: ['extended' => $panel],
        );

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

        $result = (new ToolbarDataMapper())->map(
            tag: 'capture-tag',
            title: 'Yii Debugger',
            indexUrl: '/debug/index',
            configUrl: null,
            panels: ['own' => $panel],
        );

        $mapped = $result['items'][0] ?? null;

        self::assertIsArray($mapped, 'Mapped payload must contain the panel envelope.');
        self::assertSame('7', $mapped['id'] ?? null, 'Envelope ID must win over the registry key and be coerced.');
        self::assertSame('Custom Title', $mapped['title'] ?? null, 'Envelope title must win over the panel name.');
        self::assertSame('/custom-url', $mapped['url'] ?? null, 'Envelope URL must win over the generated URL.');
    }

    public function testMapProcessesTypedPanelAfterLegacyEnvelope(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug');
        $legacy = new MinimalToolbarPanel();

        $legacy->id = 'legacy';
        $legacy->module = $module;
        $legacy->tag = 'capture-tag';

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

        $result = (new ToolbarDataMapper())->map(
            tag: 'capture-tag',
            title: 'Yii Debugger',
            indexUrl: '/debug/index',
            configUrl: null,
            panels: ['legacy' => $legacy, 'typed' => $typed],
        );

        self::assertCount(2, $result['items'], 'Panels after a legacy envelope must still be processed.');
        self::assertSame(
            'typed',
            $result['items'][1]['id'] ?? null,
            'Typed panel must follow the legacy envelope.',
        );
    }

    public function testMapRetainsLegacyFreeFormPanelEnvelope(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug');
        $panel = new MinimalToolbarPanel();

        $panel->id = 'legacy';
        $panel->module = $module;
        $panel->tag = 'capture-tag';

        $result = (new ToolbarDataMapper())->map(
            tag: 'capture-tag',
            title: 'Yii Debugger',
            indexUrl: '/debug/index',
            configUrl: '/debug/view?panel=config',
            panels: ['legacy' => $panel],
        );

        $legacyPanel = $result['items'][0] ?? null;

        self::assertIsArray(
            $legacyPanel,
            'Mapped toolbar payload must retain the free-form panel envelope.',
        );

        self::assertSame(
            'minimal',
            $legacyPanel['chip'] ?? null,
            'A free-form custom panel must remain available through the compatibility lane.',
        );
        self::assertSame(
            'legacy',
            $legacyPanel['id'] ?? null,
            'Historical panel ID defaults must still be injected.',
        );
        self::assertArrayHasKey(
            'title',
            $legacyPanel,
            'Historical panel title defaults must still be injected.',
        );
        self::assertArrayHasKey(
            'url',
            $legacyPanel,
            'Historical panel URL defaults must still be injected.',
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
                    ['extension' => 'preserved', 'items' => 'legacy'],
                    ['items' => [], 'id' => 'typed'],
                ],
            ),
            'Non-array legacy items must leave the normalized item list unchanged.',
        );
    }

    public function testPanelRejectsInvalidLegacyEnvelopes(): void
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

    protected function tearDown(): void
    {
        $this->destroyApplication();

        parent::tearDown();
    }
}
