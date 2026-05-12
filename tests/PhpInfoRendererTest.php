<?php

declare(strict_types=1);

namespace yiiunit\debug;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\widgets\phpinfo\{
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
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('panel')]
#[Group('phpinfo')]
final class PhpInfoRendererTest extends TestCase
{
    public function testRenderEmitsTocLinkPerEntry(): void
    {
        $view = $this->emptyView([
            new PhpInfoTocEntry(title: 'Overview', slug: 'phpinfo-overview'),
            new PhpInfoTocEntry(title: 'apcu', slug: 'phpinfo-apcu'),
        ]);

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

    public function testRenderModulesHtmlPassesThroughVerbatim(): void
    {
        $view = new PhpInfoView(
            sections: [],
            tocEntries: [],
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
            'data-yii-debug-phpinfo-search',
            $html,
            'Search input must carry the filter JS hook.',
        );
        self::assertStringContainsString(
            'data-yii-debug-phpinfo-empty',
            $html,
            'Empty-state hint must carry the JS hook.',
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

    public function testRenderViaNormalizerSnapshotProducesExpectedAnchors(): void
    {
        $body = '<h2>apcu</h2><table><tr><td>Version</td><td>5.1</td></tr></table>';

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
        return new PhpInfoView(sections: [], tocEntries: $entries, modulesHtml: '', configureCommand: '');
    }
}
