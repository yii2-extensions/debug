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

    protected function tearDown(): void
    {
        $this->destroyApplication();

        parent::tearDown();
    }
}
