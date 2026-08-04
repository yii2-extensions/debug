<?php

declare(strict_types=1);

namespace yii\debug\tests;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\phpinfo\{
    PhpInfoCompactModule,
    PhpInfoDataNormalizer,
    PhpInfoRenderer,
    PhpInfoSection,
    PhpInfoTile,
    PhpInfoTocEntry,
    PhpInfoToken,
    PhpInfoView,
};

/**
 * Unit tests for {@see PhpInfoRenderer} covering the TOC sidebar, the per-section composition (eyebrow + headline +
 * tiles), the tile-kind rendering branches and the Configure Command details disclosure.
 */
#[Group('panel')]
#[Group('phpinfo')]
final class PhpInfoRendererTest extends TestCase
{
    public function testRenderEmitsTocLinkPerEntry(): void
    {
        $view = $this->emptyView(
            [
                new PhpInfoTocEntry(title: 'Overview', slug: 'phpinfo-overview'),
                new PhpInfoTocEntry(title: 'apcu', slug: 'phpinfo-apcu'),
            ],
        );

        $html = PhpInfoRenderer::render($view);

        self::assertStringContainsString(
            'href="#phpinfo-overview"',
            $html,
            'TOC must link to the Overview slug.',
        );
        self::assertStringContainsString(
            'href="#phpinfo-apcu"',
            $html,
            'TOC must link to every module slug.',
        );
        self::assertStringContainsString(
            'data-toc-target="phpinfo-apcu"',
            $html,
            'TOC entries must carry the data-toc-target attribute.',
        );
    }

    public function testRenderGroupsModulesAndFallsBackToOther(): void
    {
        $html = PhpInfoRenderer::render(
            $this->emptyView(
                [
                    new PhpInfoTocEntry(title: 'Overview', slug: 'phpinfo-overview'),
                    new PhpInfoTocEntry(title: 'Core', slug: 'phpinfo-core'),
                    new PhpInfoTocEntry(title: 'date', slug: 'phpinfo-date'),
                    new PhpInfoTocEntry(title: 'PDO', slug: 'phpinfo-pdo'),
                    new PhpInfoTocEntry(title: 'vendor_extension', slug: 'phpinfo-vendor-extension'),
                ],
            ),
        );

        self::assertMatchesRegularExpression(
            '~Core &amp; Runtime.*?href="#phpinfo-core".*?href="#phpinfo-date".*?</details>~s',
            $html,
            'Runtime modules must share one collapsed navigation group.',
        );
        self::assertMatchesRegularExpression(
            '~Database.*?href="#phpinfo-pdo".*?</details>~s',
            $html,
            'Database drivers must render in the Database group.',
        );
        self::assertMatchesRegularExpression(
            '~Other.*?href="#phpinfo-vendor-extension".*?</details>~s',
            $html,
            'Unknown extensions must remain accessible in the Other group.',
        );
        self::assertStringContainsString(
            'data-yii-debug-phpinfo-toc-group="true"',
            $html,
            'Every module group must expose the JavaScript synchronization hook.',
        );
    }

    public function testRenderMarksLongOverviewValuesAsWide(): void
    {
        $section = new PhpInfoSection(
            eyebrow: 'Build',
            tiles: [
                new PhpInfoTile(
                    label: 'Build System',
                    displayValue: 'A deliberately long build-system value that needs the complete card width',
                    rawValue: 'A deliberately long build-system value that needs the complete card width',
                    kind: PhpInfoTile::KIND_TEXT,
                ),
            ],
        );

        $html = PhpInfoRenderer::render(
            new PhpInfoView(
                sections: [$section],
                tocEntries: [],
                compactModules: [],
                modulesHtml: '',
                configureCommand: '',
            ),
        );

        self::assertStringContainsString(
            'class="yii-debug-phpinfo-overview-hero-metric is-wide"',
            $html,
            'Long technical values must span the overview card instead of wrapping inside a narrow grid cell.',
        );
    }

    public function testRenderMarksOverviewAsInitialTocSelection(): void
    {
        $html = PhpInfoRenderer::render(
            $this->emptyView(
                [
                    new PhpInfoTocEntry(title: 'Overview', slug: 'phpinfo-overview'),
                    new PhpInfoTocEntry(title: 'Core', slug: 'phpinfo-core'),
                ],
            ),
        );

        self::assertStringContainsString(
            'class="yii-debug-phpinfo-toc-link is-active"',
            $html,
            'Overview must render as the initial selected view before JavaScript initializes.',
        );
        self::assertStringContainsString(
            'aria-current="page"',
            $html,
            'The initial TOC selection must be exposed to assistive technology.',
        );
        self::assertStringContainsString(
            '<span>1</span><span>modules</span>',
            $html,
            'The TOC counter must exclude the Overview entry.',
        );
    }

    public function testRenderModulesHtmlPassesThroughVerbatim(): void
    {
        $view = new PhpInfoView(
            sections: [],
            tocEntries: [],
            compactModules: [],
            modulesHtml: '<section id="phpinfo-apcu">module-body</section>',
            configureCommand: '',
        );

        $html = PhpInfoRenderer::render($view);

        self::assertStringContainsString(
            '<section id="phpinfo-apcu">module-body</section>',
            $html,
            'Modules HTML must round-trip verbatim into the main column.',
        );
    }

    public function testRenderRendersConfigureCommandWhenPresent(): void
    {
        $view = new PhpInfoView(
            sections: [],
            tocEntries: [],
            compactModules: [],
            modulesHtml: '',
            configureCommand: './configure --foo',
        );

        $html = PhpInfoRenderer::render($view);

        self::assertStringContainsString(
            'Configure Command',
            $html,
            'Configure Command details must surface.',
        );
        self::assertStringContainsString(
            './configure --foo',
            $html,
            'Configure command body must surface inside the disclosure.',
        );
    }

    public function testRenderSearchInputCarriesFilterHooks(): void
    {
        $html = PhpInfoRenderer::render($this->emptyView([]));

        self::assertStringContainsString(
            'data-yii-debug-phpinfo-search="true"',
            $html,
            'Search input must enable the filter JS hook explicitly.',
        );
        self::assertStringContainsString(
            'data-yii-debug-phpinfo-empty="true"',
            $html,
            'Empty-state hint must enable the JS hook explicitly.',
        );
        self::assertStringContainsString(
            'data-yii-debug-phpinfo-clear="true"',
            $html,
            'Search must expose an explicit clear action.',
        );
        self::assertStringContainsString(
            'data-yii-debug-phpinfo-status="true"',
            $html,
            'Search must expose a live result-count hook.',
        );
        self::assertStringContainsString(
            '<span class="yii-debug-phpinfo-search-empty" hidden data-yii-debug-phpinfo-empty="true">',
            $html,
            'Empty-state hint must remain hidden until filtering finds no matches.',
        );
        self::assertStringContainsString(
            'aria-controls="yii-debug-phpinfo-results"',
            $html,
            'Search must identify the result region it controls.',
        );
    }

    public function testRenderSectionRendersEyebrowHeader(): void
    {
        $section = new PhpInfoSection(eyebrow: 'Runtime', tiles: []);
        $view = new PhpInfoView(
            sections: [$section],
            tocEntries: [],
            compactModules: [],
            modulesHtml: '',
            configureCommand: '',
        );

        $html = PhpInfoRenderer::render($view);

        self::assertStringContainsString(
            '<span class="yii-debug-phpinfo-overview-block-eyebrow">Runtime</span>',
            $html,
            'Every overview section must retain its eyebrow header.',
        );
    }

    public function testRenderSectionWithMutedPillTile(): void
    {
        $section = new PhpInfoSection(
            eyebrow: 'Capabilities',
            tiles: [
                new PhpInfoTile(
                    label: 'Debug Build',
                    displayValue: 'no',
                    rawValue: 'no',
                    kind: PhpInfoTile::KIND_PILL_MUTED,
                ),
            ],
        );

        $view = new PhpInfoView(
            sections: [$section],
            tocEntries: [],
            compactModules: [],
            modulesHtml: '',
            configureCommand: '',
        );
        $html = PhpInfoRenderer::render($view);

        self::assertStringContainsString(
            'yii-debug-phpinfo-overview-pill',
            $html,
            'Muted pill must carry the pill CSS class.',
        );
        self::assertStringContainsString(
            'data-variant="muted"',
            $html,
            'Muted pill must carry the muted variant attribute.',
        );
    }

    public function testRenderSectionWithPathListTokens(): void
    {
        $tile = new PhpInfoTile(
            label: 'Additional .ini files parsed',
            displayValue: '/etc/a.ini, /etc/b.ini',
            rawValue: '/etc/a.ini, /etc/b.ini',
            kind: PhpInfoTile::KIND_PATH_LIST,
            tokens: [
                new PhpInfoToken(label: 'a.ini', title: '/etc/a.ini'),
                new PhpInfoToken(label: 'b.ini', title: '/etc/b.ini'),
            ],
        );

        $section = new PhpInfoSection(eyebrow: 'Configuration', tiles: [$tile]);
        $view = new PhpInfoView(
            sections: [$section],
            tocEntries: [],
            compactModules: [],
            modulesHtml: '',
            configureCommand: '',
        );
        $html = PhpInfoRenderer::render($view);

        self::assertStringContainsString(
            '>a.ini<',
            $html,
            'First token basename must render inside a code chip.',
        );
        self::assertStringContainsString(
            'title="/etc/a.ini"',
            $html,
            'First token full path must surface in the title attribute.',
        );
        self::assertStringContainsString(
            'yii-debug-phpinfo-overview-token',
            $html,
            'Tokens must carry the token CSS class.',
        );
    }

    public function testRenderSectionWithPathTileRendersCodeWithFullPathTitle(): void
    {
        $tile = new PhpInfoTile(
            label: 'Loaded Configuration File',
            displayValue: 'php.ini',
            rawValue: '/etc/php/8.5/cli/php.ini',
            kind: PhpInfoTile::KIND_PATH,
        );

        $section = new PhpInfoSection(eyebrow: 'Configuration', tiles: [$tile]);
        $view = new PhpInfoView(
            sections: [$section],
            tocEntries: [],
            compactModules: [],
            modulesHtml: '',
            configureCommand: '',
        );
        $html = PhpInfoRenderer::render($view);

        self::assertStringContainsString(
            '<code',
            $html,
            'KIND_PATH must render inside a `<code>` element.',
        );
        self::assertStringContainsString(
            'title="/etc/php/8.5/cli/php.ini"',
            $html,
            'KIND_PATH must surface the raw path in the title attribute.',
        );
        self::assertStringContainsString(
            '>php.ini<',
            $html,
            'KIND_PATH must show the basename in the visible content.',
        );
    }

    public function testRenderSectionWithSuccessPillTile(): void
    {
        $section = new PhpInfoSection(
            eyebrow: 'Capabilities',
            tiles: [
                new PhpInfoTile(
                    label: 'IPv6 Support',
                    displayValue: 'enabled',
                    rawValue: 'enabled',
                    kind: PhpInfoTile::KIND_PILL_SUCCESS,
                ),
            ],
        );

        $view = new PhpInfoView(
            sections: [$section],
            tocEntries: [],
            compactModules: [],
            modulesHtml: '',
            configureCommand: '',
        );
        $html = PhpInfoRenderer::render($view);

        self::assertStringContainsString(
            'data-variant="success"',
            $html,
            'Success pill must carry the success variant attribute.',
        );
    }

    public function testRenderSkipsConfigureCommandWhenEmpty(): void
    {
        $html = PhpInfoRenderer::render($this->emptyView([]));

        self::assertStringNotContainsString(
            'Configure Command',
            $html,
            'Empty Configure Command must drop the disclosure.',
        );
    }

    public function testRenderSummarizesCompactModulesInOverview(): void
    {
        $view = new PhpInfoView(
            sections: [],
            tocEntries: [
                new PhpInfoTocEntry(title: 'Overview', slug: 'phpinfo-overview'),
                new PhpInfoTocEntry(title: 'Core', slug: 'phpinfo-core'),
            ],
            compactModules: [
                new PhpInfoCompactModule(
                    title: 'calendar',
                    slug: 'phpinfo-calendar',
                    tiles: [
                        new PhpInfoTile(
                            label: 'Calendar support',
                            displayValue: 'enabled',
                            rawValue: 'enabled',
                            kind: PhpInfoTile::KIND_PILL_SUCCESS,
                        ),
                    ],
                ),
            ],
            modulesHtml: '<section id="phpinfo-core">Core</section>',
            configureCommand: '',
        );

        $html = PhpInfoRenderer::render($view);

        self::assertStringContainsString(
            'Loaded extensions',
            $html,
            'Facts-only modules must surface in the Overview.',
        );
        self::assertStringContainsString(
            'id="phpinfo-calendar"',
            $html,
            'Summarized modules must retain their original deep-link anchor.',
        );
        self::assertStringContainsString(
            'data-yii-debug-phpinfo-compact-module="true"',
            $html,
            'Summarized modules must expose the search hook.',
        );
        self::assertStringContainsString(
            'data-yii-debug-phpinfo-extensions="true"',
            $html,
            'Summarized modules must live in an identifiable disclosure.',
        );
        self::assertStringContainsString(
            'class="yii-debug-ext-pill is-on"',
            $html,
            'Summaries must reuse the Config panel pill, enabled variant.',
        );
        self::assertStringContainsString(
            '<span class="yii-debug-ext-pill-state">on</span>',
            $html,
            'A module without a version must fall back to the on/off state.',
        );
        self::assertStringContainsString(
            '<span class="yii-debug-sr-only">Calendar support: enabled</span>',
            $html,
            'Facts the pill cannot show must stay reachable and searchable.',
        );
        self::assertStringNotContainsString(
            'href="#phpinfo-calendar"',
            $html,
            'Summarized modules must not keep an almost-empty sidebar destination.',
        );
        self::assertStringContainsString(
            '<span>2</span><span>modules</span>',
            $html,
            'The sidebar total must include detailed and summarized modules.',
        );
        self::assertStringContainsString(
            '1 in Overview',
            $html,
            'The sidebar must explain where summarized modules moved.',
        );
    }

    public function testRenderSurfacesVersionAndDisabledStateInCompactPills(): void
    {
        $view = new PhpInfoView(
            sections: [],
            tocEntries: [new PhpInfoTocEntry(title: 'Overview', slug: 'phpinfo-overview')],
            compactModules: [
                new PhpInfoCompactModule(
                    title: 'pdo_sqlite',
                    slug: 'phpinfo-pdo-sqlite',
                    tiles: [
                        new PhpInfoTile(
                            label: 'PDO Driver for SQLite 3.x',
                            displayValue: 'enabled',
                            rawValue: 'enabled',
                            kind: PhpInfoTile::KIND_PILL_SUCCESS,
                        ),
                        new PhpInfoTile(
                            label: 'SQLite Library',
                            displayValue: '3.53.3',
                            rawValue: '3.53.3',
                            kind: PhpInfoTile::KIND_TEXT,
                        ),
                    ],
                ),
                new PhpInfoCompactModule(
                    title: 'sysvshm',
                    slug: 'phpinfo-sysvshm',
                    tiles: [
                        new PhpInfoTile(
                            label: 'sysvshm support',
                            displayValue: 'disabled',
                            rawValue: 'disabled',
                            kind: PhpInfoTile::KIND_PILL_MUTED,
                        ),
                    ],
                ),
            ],
            modulesHtml: '',
            configureCommand: '',
        );

        $html = PhpInfoRenderer::render($view);

        self::assertStringContainsString(
            '<span class="yii-debug-ext-pill-state">3.53.3</span>',
            $html,
            'The state slot must prefer the version over a redundant on.',
        );
        self::assertStringContainsString(
            'class="yii-debug-ext-pill is-off"',
            $html,
            'A module reporting only a muted fact must render as `is-off`.',
        );
        self::assertStringContainsString(
            'title="PDO Driver for SQLite 3.x: enabled · SQLite Library: 3.53.3"',
            $html,
            'Every fact must survive in the tooltip.',
        );
    }

    public function testRenderViaNormalizerSnapshotProducesExpectedAnchors(): void
    {
        $body = <<<'HTML'
        <h2>apcu</h2>
        <table>
        <tr><td>Version</td><td>5.1</td></tr>
        <tr><td>Debug</td><td>disabled</td></tr>
        <tr><td>MMAP</td><td>enabled</td></tr>
        </table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput($body, '8.5.3', 'cli', 'Linux', '128M');
        $html = PhpInfoRenderer::render($view);

        self::assertStringContainsString(
            'id="phpinfo-overview"',
            $html,
            'Overview anchor must surface in the rendered shell.',
        );
        self::assertStringContainsString(
            'id="phpinfo-apcu"',
            $html,
            'Module anchor must surface in the rendered shell.',
        );
        self::assertStringContainsString(
            'href="#phpinfo-apcu"',
            $html,
            'TOC must link to the module anchor.',
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockWebApplication();
    }

    protected function tearDown(): void
    {
        $this->destroyApplication();

        parent::tearDown();
    }

    /**
     * @param list<PhpInfoTocEntry> $entries
     */
    private function emptyView(array $entries): PhpInfoView
    {
        return new PhpInfoView(
            sections: [],
            tocEntries: $entries,
            compactModules: [],
            modulesHtml: '',
            configureCommand: '',
        );
    }
}
