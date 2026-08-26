<?php

declare(strict_types=1);

namespace yii\debug\tests\vite;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\VitePanel;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see VitePanel} covering per-capture visibility and detail/toolbar presentation.
 */
#[Group('panel')]
#[Group('vite')]
final class VitePanelTest extends TestCase
{
    public function testGetComponentsReturnsEmptyListBeforeHydration(): void
    {
        $panel = $this->makePanel(VitePanel::class);

        self::assertSame(
            [],
            $panel->getComponents(),
            'A panel without a hydrated snapshot must expose no Vite components.',
        );
    }

    public function testGetDetailRendersCapturedConfigurationAndChunks(): void
    {
        $panel = $this->makePanel(VitePanel::class);

        $panel->hydrate(
            self::snapshot(
                [
                    self::component(
                        [
                            'mode' => 'production',
                            'devServerUrl' => null,
                            'manifestPath' => '/app/public/build/.vite/manifest.json',
                            'includeViteClient' => null,
                            'modulePreload' => false,
                            'chunks' => [
                                [
                                    'name' => 'resources/js/app.js',
                                    'file' => 'assets/app-def.js',
                                    'cssCount' => 1,
                                    'imports' => 2,
                                    'isEntry' => true,
                                ],
                                [
                                    'name' => '_shared.js',
                                    'file' => '',
                                    'cssCount' => 0,
                                    'imports' => 0,
                                    'isEntry' => false,
                                ],
                            ],
                        ],
                    ),
                ],
            ),
        );

        $html = $panel->getDetail();

        self::assertMatchesRegularExpression(
            '~<h1 class="yii-debug-sr-only">\s*Vite\s*</h1>~',
            $html,
            'The detail must expose an accessible page heading.',
        );
        self::assertStringContainsString(
            'resources/js/app.js',
            $html,
            'Captured entry points must surface in the detail.',
        );
        self::assertStringContainsString(
            'assets/app-def.js',
            $html,
            'Manifest output files must surface in the chunk table.',
        );
        self::assertStringContainsString(
            'scope="col"',
            $html,
            'Chunk headers must identify their table-column scope.',
        );
        self::assertStringContainsString(
            'class="yii-debug-table yii-debug-table-mono yii-debug-table-vite-overview"',
            $html,
            'Configuration must use the shared panel table with a readable label column.',
        );
        self::assertStringContainsString(
            'Component ID',
            $html,
            'The exact application-component ID must remain available as technical metadata.',
        );
        self::assertDoesNotMatchRegularExpression(
            '~<h2[^>]*>\s*vite\s*</h2>~',
            $html,
            'The sidebar already identifies the panel, so the component ID must not become a redundant title.',
        );
        self::assertStringContainsString(
            'Disabled',
            $html,
            'Disabled production options must remain distinguishable from unavailable options.',
        );
        self::assertStringContainsString(
            'Not applicable',
            $html,
            'Development-only options must be identified as not applicable in production.',
        );
    }

    public function testGetDetailRendersDevelopmentModeWithoutBuildChunks(): void
    {
        $panel = $this->makePanel(VitePanel::class);

        $panel->hydrate(self::snapshot([self::component()]));

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'http://localhost:5173',
            $html,
            'Development configuration must surface the active dev server.',
        );
        self::assertStringContainsString(
            'Development mode resolves entry points through the dev server',
            $html,
            'Development mode must explain why no build chunks were captured.',
        );
        self::assertStringContainsString(
            'Not applicable',
            $html,
            'Production-only options must be identified as not applicable in development.',
        );
    }

    public function testGetDetailRendersEmptySnapshotState(): void
    {
        $panel = $this->makePanel(VitePanel::class);

        $panel->hydrate(self::snapshot([]));

        self::assertStringContainsString(
            'No Vite integrations captured',
            $panel->getDetail(),
            'An empty snapshot must render a clear fallback when opened directly.',
        );
    }

    public function testGetDetailReportsMissingProductionManifest(): void
    {
        $panel = $this->makePanel(VitePanel::class);

        $panel->hydrate(
            self::snapshot(
                [
                    self::component(
                        [
                            'mode' => 'production',
                            'devServerUrl' => null,
                            'manifestPath' => '/app/public/build/.vite/manifest.json',
                            'includeViteClient' => null,
                        ],
                    ),
                ],
            ),
        );

        self::assertStringContainsString(
            'The Vite manifest is missing or empty',
            $panel->getDetail(),
            'Production mode without chunks must explain how to populate the manifest.',
        );
    }

    public function testGetDetailReportsUnavailableRuntimeInspection(): void
    {
        $panel = $this->makePanel(VitePanel::class);

        $panel->hydrate(
            self::snapshot(
                [
                    self::component(
                        [
                            'inspectionAvailable' => false,
                            'mode' => 'unknown',
                            'entrypoints' => [],
                            'baseUrl' => '',
                            'devServerUrl' => null,
                            'includeViteClient' => null,
                            'modulePreload' => null,
                        ],
                    ),
                ],
            ),
        );

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'Runtime inspection is unavailable for this component',
            $html,
            'Unavailable public inspection must be explained rather than silently showing empty values.',
        );
        self::assertStringContainsString(
            'role="status"',
            $html,
            'The inspection notice must expose its status semantics to assistive technology.',
        );
    }

    public function testGetDetailSummarizesMixedComponentModes(): void
    {
        $panel = $this->makePanel(VitePanel::class);

        $panel->hydrate(
            self::snapshot(
                [
                    self::component(['id' => 'frontend', 'mode' => 'development']),
                    self::component(['id' => 'admin', 'mode' => 'production']),
                ],
            ),
        );

        self::assertStringContainsString(
            'Mixed',
            $panel->getDetail(),
            'Different component modes must be summarized as mixed in the detail header.',
        );
    }

    public function testGetNameAndIconReturnToolbarMetadata(): void
    {
        $panel = $this->makePanel(VitePanel::class);

        self::assertSame(
            'Vite',
            $panel->getName(),
            "Panel name must be 'Vite'.",
        );
        self::assertSame(
            'brand-javascript',
            $panel->getToolbarIcon(),
            "Toolbar icon key must be 'brand-javascript'.",
        );
    }

    public function testGetToolbarItemsAggregateMatchingModes(): void
    {
        $panel = $this->makePanel(VitePanel::class);

        $panel->hydrate(
            self::snapshot(
                [
                    self::component(['id' => 'frontend', 'mode' => 'production']),
                    self::component(['id' => 'admin', 'mode' => 'production']),
                ],
            ),
        );

        self::assertSame(
            [
                [
                    'title' => 'Vite mode',
                    'value' => '2 components · Production',
                ],
            ],
            $this->invoke($panel, 'getToolbarItems'),
            'Matching modes must retain the shared mode alongside the component count.',
        );
    }

    public function testGetToolbarItemsReportMixedModes(): void
    {
        $panel = $this->makePanel(VitePanel::class);

        $panel->hydrate(
            self::snapshot(
                [
                    self::component(['id' => 'frontend', 'mode' => 'development']),
                    self::component(['id' => 'admin', 'mode' => 'production']),
                ],
            ),
        );

        self::assertSame(
            [
                [
                    'title' => 'Vite mode',
                    'value' => '2 components · Mixed',
                ],
            ],
            $this->invoke($panel, 'getToolbarItems'),
            'Different component modes must be summarized as mixed.',
        );
    }

    public function testGetToolbarItemsReturnConciseSingleMode(): void
    {
        $panel = $this->makePanel(VitePanel::class);

        $panel->hydrate(self::snapshot([self::component()]));

        self::assertSame(
            [
                [
                    'title' => 'Vite mode',
                    'value' => 'Development',
                ],
            ],
            $this->invoke($panel, 'getToolbarItems'),
            'A single component must expose only its runtime mode.',
        );
    }

    public function testGetToolbarItemsReturnEmptyListWithoutComponents(): void
    {
        $panel = $this->makePanel(VitePanel::class);

        $panel->hydrate(self::snapshot([]));

        self::assertSame(
            [],
            $this->invoke($panel, 'getToolbarItems'),
            'An empty Vite snapshot must not add a toolbar chip.',
        );
    }

    public function testGetToolbarItemsReturnUnknownWhenInspectionIsUnavailable(): void
    {
        $panel = $this->makePanel(VitePanel::class);

        $panel->hydrate(
            self::snapshot(
                [
                    self::component(
                        [
                            'inspectionAvailable' => false,
                            'mode' => 'unknown',
                        ],
                    ),
                ],
            ),
        );

        self::assertSame(
            [
                [
                    'title' => 'Vite mode',
                    'value' => 'Unknown',
                ],
            ],
            $this->invoke($panel, 'getToolbarItems'),
            'Unavailable inspection must not imply a development or production mode.',
        );
    }

    public function testHasContentReturnsFalseForEmptySnapshot(): void
    {
        $panel = $this->makePanel(VitePanel::class);

        $panel->hydrate(self::snapshot([]));

        self::assertFalse(
            $panel->hasContent(),
            'An empty Vite snapshot must hide the sidebar entry.',
        );
    }

    public function testHasContentReturnsTrueForCapturedComponent(): void
    {
        $panel = $this->makePanel(VitePanel::class);

        $panel->hydrate(self::snapshot([self::component()]));

        self::assertTrue(
            $panel->hasContent(),
            'A captured Vite component must surface the sidebar entry.',
        );
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function component(array $overrides = []): array
    {
        return [
            ...[
                'id' => 'vite',
                'class' => 'PHPForge\\Vite\\Vite',
                'implementation' => 'modern',
                'inspectionAvailable' => true,
                'mode' => 'development',
                'entrypoints' => ['resources/js/app.js'],
                'baseUrl' => '/build',
                'devServerUrl' => 'http://localhost:5173',
                'manifestPath' => '',
                'includeViteClient' => true,
                'modulePreload' => null,
                'chunks' => [],
            ],
            ...$overrides,
        ];
    }

    /**
     * @param list<array<string, mixed>> $components
     *
     * @return array{components: list<array<string, mixed>>}
     */
    private static function snapshot(array $components): array
    {
        return ['components' => $components];
    }
}
