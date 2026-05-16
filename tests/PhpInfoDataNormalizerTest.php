<?php

declare(strict_types=1);

namespace yii\debug\tests;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\phpinfo\{PhpInfoDataNormalizer, PhpInfoTile};

/**
 * Unit tests for {@see PhpInfoDataNormalizer} covering the parsing of the raw {@see phpinfo()} HTML output, the
 * tile-kind classification (pill / path / token list) and the wrapping of module blocks into deep-linkable sections.
 */
#[Group('panel')]
#[Group('phpinfo')]
final class PhpInfoDataNormalizerTest extends TestCase
{
    public function testFromOutputBuildsHeroSectionWithVersionHeadline(): void
    {
        $body = '<table><tr><td>Server API</td><td>cli</td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            '8.5.3',
            'cli',
            'Linux',
            '128M',
        );

        self::assertNotEmpty(
            $view->sections,
            'Hero sections must be present.',
        );
        self::assertSame(
            'PHP version',
            $view->sections[0]->eyebrow,
            "First section must be the 'PHP' version hero.",
        );
        self::assertSame(
            '8.5.3',
            $view->sections[0]->headline,
            "Headline must echo the active 'PHP_VERSION'.",
        );
    }

    public function testFromOutputClassifiesEnabledAsSuccessPill(): void
    {
        $body = '<table><tr><td>IPv6 Support</td><td>enabled</td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        $capabilitiesSection = null;

        foreach ($view->sections as $section) {
            if ($section->eyebrow === 'Capabilities') {
                $capabilitiesSection = $section;
            }
        }

        self::assertNotNull(
            $capabilitiesSection,
            'Capabilities section must surface.',
        );

        $ipv6Tile = null;

        foreach ($capabilitiesSection->tiles as $tile) {
            if ($tile->label === 'IPv6 Support') {
                $ipv6Tile = $tile;
            }
        }

        self::assertNotNull(
            $ipv6Tile,
            'IPv6 tile must be present.',
        );
        self::assertSame(
            PhpInfoTile::KIND_PILL_SUCCESS,
            $ipv6Tile->kind,
            "'enabled' must classify as the success pill.",
        );
    }

    public function testFromOutputClassifiesPathListWithBasenameTokens(): void
    {
        $body = '<table><tr><td>Additional .ini files parsed</td><td>/etc/php/apcu.ini, /etc/php/oci.ini</td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        $configSection = null;

        foreach ($view->sections as $section) {
            if ($section->eyebrow === 'Configuration') {
                $configSection = $section;
            }
        }

        self::assertNotNull(
            $configSection,
            'Configuration section must surface.',
        );

        $tile = $configSection->tiles[0] ?? null;

        self::assertNotNull(
            $tile,
            'Tile must surface for the parsed entry.',
        );
        self::assertSame(
            PhpInfoTile::KIND_PATH_LIST,
            $tile->kind,
            "Path list with comma + leading '/' must classify as KIND_PATH_LIST.",
        );
        self::assertCount(
            2,
            $tile->tokens,
            'Path list must produce one token per entry.',
        );
        self::assertSame(
            'apcu.ini',
            $tile->tokens[0]->label,
            'Basename must surface as the token label.',
        );
        self::assertSame(
            '/etc/php/apcu.ini',
            $tile->tokens[0]->title,
            'Full path must survive in the token title.',
        );
    }

    public function testFromOutputExtractsConfigureCommand(): void
    {
        $body = '<table><tr><td>Configure Command</td><td>./configure --foo=bar</td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            ''
        );

        self::assertSame(
            './configure --foo=bar',
            $view->configureCommand,
            'Configure Command must surface verbatim.',
        );
    }

    public function testFromOutputProducesTocEntryPerModuleH2(): void
    {
        $body = '<h2>apcu</h2><table><tr><td>Version</td><td>5.1.0</td></tr></table>'
            . '<h2>Core</h2><table><tr><td>PHP Version</td><td>8.5</td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        $titles = [];

        foreach ($view->tocEntries as $entry) {
            $titles[] = $entry->title;
        }

        self::assertSame(
            [
                'Overview',
                'apcu',
                'Core',
            ],
            $titles,
            "Every '<h2>' must produce a TOC entry.",
        );
    }

    public function testFromOutputProducesUniqueSlugsForTocEntries(): void
    {
        $body = '<h2>apcu</h2><table></table>';

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        $slugs = [];

        foreach ($view->tocEntries as $entry) {
            $slugs[] = $entry->slug;
        }

        self::assertSame(
            [
                'phpinfo-overview',
                'phpinfo-apcu',
            ],
            $slugs,
            "Slugs must follow the 'phpinfo-<title>' convention."
        );
    }

    public function testFromOutputSkipsPhpLogoRows(): void
    {
        $body = '<table><tr><td>PHP Logo GUID</td><td>some-guid</td></tr><tr><td>SAPI</td><td>cli</td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            ''
        );

        self::assertNotSame([], $view->sections, 'Normalized output must expose at least one hero section.');

        $heroLabels = [];

        foreach ($view->sections[0]->tiles as $tile) {
            $heroLabels[] = $tile->label;
        }

        self::assertNotContains(
            'PHP Logo GUID',
            $heroLabels,
            "'PHP' Logo entries must be filtered out.",
        );
    }

    public function testFromOutputWrapsModulesHtmlWithSectionChrome(): void
    {
        $body = '<h2>apcu</h2><table><tr><td>Version</td><td>5.1</td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        self::assertStringContainsString(
            'yii-debug-phpinfo-module',
            $view->modulesHtml,
            'Modules HTML must wrap blocks with the module class.',
        );
        self::assertStringContainsString(
            'yii-debug-table-wrap',
            $view->modulesHtml,
            'Modules HTML must wrap tables in the panel chrome.',
        );
        self::assertStringContainsString(
            'id="phpinfo-apcu"',
            $view->modulesHtml,
            'Modules HTML must carry the slug id for TOC anchors.',
        );
    }
}
