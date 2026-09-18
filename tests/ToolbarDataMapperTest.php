<?php

declare(strict_types=1);

namespace yii\debug\tests;

use Override;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use yii\debug\exception\Message;
use yii\debug\{Module, Panel, ToolbarDataMapper};
use yii\debug\panels\ProviderPanel;
use yii\debug\tests\provider\ToolbarEnvelopeProvider;
use yii\debug\tests\support\TestCase;

use function array_column;
use function array_keys;

/**
 * Unit tests for {@see ToolbarDataMapper} serializing Yii2 panel envelopes through the typed toolbar contract.
 *
 * {@see ToolbarEnvelopeProvider} for test case data providers.
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

    public function testMapDropsUnknownEnvelopeFields(): void
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
                    'chip' => 'panel-value',
                    'items' => [['chip' => 'item-value', 'label' => 'Count', 'value' => 42]],
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
            ['id', 'title', 'url', 'items'],
            array_keys($result['items'][0] ?? []),
            'Panel envelope must keep the typed keys only.',
        );
        self::assertSame(
            [['label' => 'Count', 'value' => '42', 'status' => 'default']],
            $result['items'][0]['items'] ?? null,
            'Item envelope must keep the typed keys only, with the value coerced and the default status applied.',
        );
    }

    public function testMapFlagsExtensionChipsAndLeavesBuiltInChipsInline(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug');

        $result = ToolbarDataMapper::create('capture-tag', 'Yii Debugger')
            ->withNavigation('/debug/index')
            ->map(
                [
                    'request' => $this->builtInPanel($module, 'request', 'Request'),
                    'vite' => $this->extensionPanel($module, 'vite', 'Vite'),
                ],
            );

        self::assertArrayNotHasKey(
            'extension',
            $result['items'][0] ?? [],
            'Inline chips must omit the key.',
        );
        self::assertTrue(
            $result['items'][1]['extension'] ?? null,
            'Provider-backed chips must carry `true`.',
        );
    }

    public function testMapFlagsTheErrorChipOfAnExtensionPanel(): void
    {
        $this->mockWebApplication();

        $panel = new class extends ProviderPanel {
            #[Override]
            public function getName(): string
            {
                return 'Vite';
            }

            #[Override]
            public function getToolbarData(): array
            {
                return ['items' => 'free-form'];
            }
        };

        $panel->id = 'vite';
        $panel->module = new Module('debug');
        $panel->tag = 'capture-tag';

        $result = ToolbarDataMapper::create('capture-tag', 'Yii Debugger')
            ->withNavigation('/debug/index')
            ->map(['vite' => $panel]);

        self::assertTrue(
            $result['items'][0]['extension'] ?? null,
            'A rejected envelope must keep its Extensions grouping.',
        );
    }

    public function testMapKeepsSiblingChipsIntactAroundAMalformedEnvelope(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug');

        $result = ToolbarDataMapper::create('capture-tag', 'Yii Debugger')
            ->withNavigation('/debug/index')
            ->map(
                [
                    'request' => $this->builtInPanel($module, 'request', 'Request'),
                    'broken' => $this->malformedPanel($module, 'broken', 'Broken'),
                    'log' => $this->builtInPanel($module, 'log', 'Log'),
                ],
            );

        self::assertSame(
            ['Request', 'Broken', 'Log'],
            array_column($result['items'], 'title'),
            'Order and count must survive the rejected envelope.',
        );
        self::assertSame(
            [['label' => 'Request', 'value' => 'Request', 'status' => 'default']],
            $result['items'][0]['items'] ?? null,
            'Chips before the rejected envelope must stay untouched.',
        );
        self::assertSame(
            [['label' => 'Log', 'value' => 'Log', 'status' => 'default']],
            $result['items'][2]['items'] ?? null,
            'Chips after the rejected envelope must stay untouched.',
        );
    }

    public function testMapKeepsTheRegisteredPanelOrder(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug');

        $result = ToolbarDataMapper::create('capture-tag', 'Yii Debugger')
            ->withNavigation('/debug/index')
            ->map(
                [
                    'request' => $this->builtInPanel($module, 'request', 'Request'),
                    'vite' => $this->extensionPanel($module, 'vite', 'Vite'),
                    'mail' => $this->builtInPanel($module, 'mail', 'Mail'),
                    'inertia' => $this->extensionPanel($module, 'inertia', 'Inertia'),
                    'log' => $this->builtInPanel($module, 'log', 'Log'),
                ],
            );

        self::assertSame(
            ['Request', 'Vite', 'Mail', 'Inertia', 'Log'],
            array_column($result['items'], 'title'),
            'Order: exactly the one the module resolved.',
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

    /**
     * @param array<string, mixed> $envelope Envelope that breaks the typed toolbar contract.
     * @param string $field Envelope field named in the diagnostic.
     */
    #[DataProviderExternal(ToolbarEnvelopeProvider::class, 'malformed')]
    public function testMapRendersAnErrorChipForAMalformedEnvelope(array $envelope, string $field): void
    {
        $this->mockWebApplication();

        $result = ToolbarDataMapper::create('capture-tag', 'Yii Debugger')
            ->withNavigation('/debug/index')
            ->map(['broken' => $this->malformedPanel(new Module('debug'), 'broken', 'Broken', $envelope)]);

        self::assertCount(
            1,
            $result['items'],
            'The rejected envelope must still occupy its chip slot.',
        );
        self::assertSame(
            'broken',
            $result['items'][0]['id'] ?? null,
            'Chip must keep the registered panel ID.',
        );
        self::assertSame(
            'Broken',
            $result['items'][0]['title'] ?? null,
            'Chip title must fall back to the panel name.',
        );
        self::assertSame(
            [
                [
                    'label' => 'Broken',
                    'value' => 'error',
                    'status' => 'danger',
                    'title' => Message::TOOLBAR_ENVELOPE_INVALID->getMessage('broken', $field),
                ],
            ],
            $result['items'][0]['items'] ?? null,
            'Error chip must mirror the base-class shape and name the offending field.',
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

    public function testWithMethodsLeaveTheSourceMapperUntouched(): void
    {
        $this->mockWebApplication();

        $base = ToolbarDataMapper::create('capture-tag', 'Yii Debugger')->withNavigation('/debug/index');

        $derived = $base
            ->withPresentation('top', 80)
            ->withBranding('/assets/svg/yii.svg');

        self::assertNotSame(
            $base,
            $derived,
            'Each fluent method must return a copy.',
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

    public function testWithNavigationFallsBackToTheHistoryUrlForTheConfigUrl(): void
    {
        $this->mockWebApplication();

        $result = ToolbarDataMapper::create('capture-tag', 'Yii Debugger')
            ->withNavigation('/debug/index')
            ->map([]);

        self::assertSame(
            '/debug/index',
            $result['configUrl'],
            'Debug Core requires a non-`null` config URL.',
        );
    }

    protected function tearDown(): void
    {
        $this->destroyApplication();

        parent::tearDown();
    }

    /**
     * Creates a built-in diagnostics panel emitting a single toolbar chip under the given name.
     */
    private function builtInPanel(Module $module, string $id, string $name): Panel
    {
        $panel = new class extends Panel {
            public string $panelName = '';

            #[Override]
            public function getName(): string
            {
                return $this->panelName;
            }

            #[Override]
            public function getToolbarData(): array
            {
                return ['items' => [['label' => $this->panelName, 'value' => $this->panelName]]];
            }
        };

        $panel->panelName = $name;
        $panel->id = $id;
        $panel->module = $module;
        $panel->tag = 'capture-tag';

        return $panel;
    }

    /**
     * Creates a provider-backed extension panel emitting a single toolbar chip under the given name.
     */
    private function extensionPanel(Module $module, string $id, string $name): Panel
    {
        $panel = new class extends ProviderPanel {
            public string $panelName = '';

            #[Override]
            public function getName(): string
            {
                return $this->panelName;
            }

            #[Override]
            public function getToolbarData(): array
            {
                return ['items' => [['value' => $this->panelName]]];
            }
        };

        $panel->panelName = $name;
        $panel->id = $id;
        $panel->module = $module;
        $panel->tag = 'capture-tag';

        return $panel;
    }

    /**
     * Creates a panel whose envelope breaks the typed toolbar contract.
     *
     * @param array<string, mixed> $envelope Envelope returned by `getToolbarData()`.
     */
    private function malformedPanel(
        Module $module,
        string $id,
        string $name,
        array $envelope = ['items' => 'free-form'],
    ): Panel {
        $panel = new class extends Panel {
            public string $panelName = '';

            /**
             * @var array<string, mixed>
             */
            public array $envelope = [];

            #[Override]
            public function getName(): string
            {
                return $this->panelName;
            }

            #[Override]
            public function getToolbarData(): array
            {
                return $this->envelope;
            }
        };

        $panel->panelName = $name;
        $panel->envelope = $envelope;
        $panel->id = $id;
        $panel->module = $module;
        $panel->tag = 'capture-tag';

        return $panel;
    }
}
