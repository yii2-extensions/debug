<?php

declare(strict_types=1);

namespace yii\debug\tests;

use PHPUnit\Framework\Attributes\Group;
use Xepozz\InternalMocker\MockerState;
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\phpinfo\{PhpInfoDataNormalizer, PhpInfoTile, PhpInfoView};

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

    public function testFromOutputClassifiesAndEnhancesModuleTables(): void
    {
        $body = '<h2>curl</h2>'
            . '<table>'
            . '<tr><td class="e">cURL support</td><td class="v">enabled</td></tr>'
            . '<tr><td class="e">Features</td></tr>'
            . '<tr><td class="e">HTTP2</td><td class="v">Yes</td></tr>'
            . '</table>'
            . '<table>'
            . '<tr class="h"><th>Directive</th><th>Local Value</th><th>Master Value</th></tr>'
            . '<tr><td class="e">curl.cainfo</td><td class="v"><i>no value</i></td><td class="v"><i>no value</i></td></tr>'
            . '</table>';

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertStringContainsString(
            'yii-debug-phpinfo-table-section is-facts',
            $view->modulesHtml,
            'Two-column module metadata must use the compact facts presentation.',
        );
        self::assertStringContainsString(
            '<span>Module information</span><span class="yii-debug-phpinfo-table-section-count">2 values</span>',
            $view->modulesHtml,
            'Facts must expose a descriptive heading and exclude subsection rows from the value count.',
        );
        self::assertStringContainsString(
            'class="yii-debug-phpinfo-status-pill" data-variant="success">enabled</span>',
            $view->modulesHtml,
            'Enabled module capabilities must render as success pills.',
        );
        self::assertStringContainsString(
            'class="yii-debug-phpinfo-fact-subheading-cell">Features</th>',
            $view->modulesHtml,
            'Single-cell labels inside fact tables must become full-width subsection headings.',
        );
        self::assertStringContainsString(
            'yii-debug-phpinfo-table-section is-directives',
            $view->modulesHtml,
            'Local/master configuration tables must retain a dense directives presentation.',
        );
        self::assertStringContainsString(
            '<span>Configuration directives</span><span class="yii-debug-phpinfo-table-section-count">1 directive</span>',
            $view->modulesHtml,
            'Directive tables must surface an accurate directive count.',
        );
    }

    public function testFromOutputClassifiesDisabledAsMutedPill(): void
    {
        $body = '<table><tr><td>Debug Build</td><td>disabled</td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        $tile = $this->findTileByLabel($view, 'Debug Build');

        self::assertNotNull(
            $tile,
            'Debug Build tile must surface.',
        );
        self::assertSame(
            PhpInfoTile::KIND_PILL_MUTED,
            $tile->kind,
            "'disabled' values must classify as the muted pill.",
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

    public function testFromOutputClassifiesTokenListWithShortCommaSeparatedValues(): void
    {
        $body = '<table><tr><td>Registered PHP Streams</td><td>https,ftps,ssh2</td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        $tile = $this->findTileByLabel($view, 'Registered PHP Streams');

        self::assertNotNull(
            $tile,
            'Registered PHP Streams tile must surface.',
        );
        self::assertSame(
            PhpInfoTile::KIND_TOKEN_LIST,
            $tile->kind,
            'Comma-separated short tokens (≤32 chars, no whitespace) must classify as KIND_TOKEN_LIST.',
        );
        self::assertCount(
            3,
            $tile->tokens,
            'Token list must produce one token per comma-separated entry.',
        );
    }

    public function testFromOutputDoesNotRedactOrdinaryModuleDirectives(): void
    {
        $body = '<h2>session</h2>'
            . '<table>'
            . '<tr class="h"><th>Directive</th><th>Local Value</th><th>Master Value</th></tr>'
            . '<tr><td class="e">session.cookie_path</td><td class="v">/</td><td class="v">/</td></tr>'
            . '</table>';

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertStringNotContainsString(
            'yii-debug-phpinfo-redacted',
            $view->modulesHtml,
            'Sensitive-name detection must not hide ordinary lowercase PHP directives.',
        );
        self::assertStringContainsString(
            '<td class="v">/</td><td class="v">/</td>',
            $view->modulesHtml,
            'Ordinary directive values must remain intact.',
        );
    }

    public function testFromOutputDowngradesTokenListToTextWhenTokenContainsWhitespace(): void
    {
        $body = '<table><tr><td>Registered PHP Streams</td><td>https,ftp with space,ssh2</td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        $tile = $this->findTileByLabel($view, 'Registered PHP Streams');

        self::assertNotNull(
            $tile,
            'Registered PHP Streams tile must surface.',
        );
        self::assertSame(
            PhpInfoTile::KIND_TEXT,
            $tile->kind,
            "Token-list candidates with whitespace inside an entry must fall back to 'KIND_TEXT'.",
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

    public function testFromOutputKeepsAbsolutePathVerbatimWhenHomeNotResolved(): void
    {
        $body = '<table><tr><td>Loaded Configuration File</td><td>/etc/php/cli/php.ini</td></tr></table>';

        unset($_SERVER['HOME'], $_SERVER['USERPROFILE']);

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        $tile = $this->findTileByLabel($view, 'Loaded Configuration File');

        self::assertNotNull(
            $tile,
            'Loaded Configuration File tile must surface.',
        );
        self::assertSame(
            '/etc/php/cli/php.ini',
            $tile->displayValue,
            'Paths outside the resolved home directory must surface verbatim.',
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

    public function testFromOutputRedactsSensitiveEnvironmentAndRuntimeVariables(): void
    {
        $body = '<h2>PHP Variables</h2>'
            . '<table>'
            . '<tr class="h"><th>Variable</th><th>Value</th></tr>'
            . '<tr><td class="e">$_COOKIE[\'XSRF-TOKEN\']</td><td class="v">sensitive-cookie-value</td></tr>'
            . '<tr><td class="e">APP_KEY</td><td class="v">sensitive-app-key</td></tr>'
            . '<tr><td class="e">$_SERVER[\'PHP_AUTH_PW\']</td><td class="v">sensitive-basic-auth-value</td></tr>'
            . '<tr><td class="e">APP_NAME</td><td class="v">Yii application</td></tr>'
            . '</table>';

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');

        self::assertStringNotContainsString(
            'sensitive-cookie-value',
            $view->modulesHtml,
            'Cookie values must never reach the rendered phpinfo HTML.',
        );
        self::assertStringNotContainsString(
            'sensitive-app-key',
            $view->modulesHtml,
            'Credential-like environment values must never reach the rendered phpinfo HTML.',
        );
        self::assertStringNotContainsString(
            'sensitive-basic-auth-value',
            $view->modulesHtml,
            'PHP basic-auth credentials must never reach the rendered phpinfo HTML.',
        );
        self::assertStringContainsString(
            'aria-label="Sensitive value hidden">redacted</span>',
            $view->modulesHtml,
            'Redacted variables must expose an accessible non-secret placeholder.',
        );
        self::assertStringContainsString(
            '>Yii application</td>',
            $view->modulesHtml,
            'Ordinary environment values must remain available.',
        );
    }

    public function testFromOutputResolvesHomeDirectoryFromPosixWhenEnvUnset(): void
    {
        $body = '<table><tr><td>Loaded Configuration File</td><td>/tmp/php.ini</td></tr></table>';

        unset($_SERVER['HOME'], $_SERVER['USERPROFILE']);
        putenv('HOME');
        putenv('USERPROFILE');

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        $tile = $this->findTileByLabel($view, 'Loaded Configuration File');

        self::assertNotNull(
            $tile,
            'Loaded Configuration File tile must surface.',
        );
        self::assertSame(
            PhpInfoTile::KIND_PATH,
            $tile->kind,
            "Without env signals, 'resolveHomeDirectory()' must still produce a PATH tile (the posix fallback "
            . 'or empty home are both acceptable).',
        );
    }

    public function testFromOutputSeparatesPhpCreditsFromPhpVariables(): void
    {
        $body = '<h2>PHP Variables</h2><table><tr><td>Variable</td><td>Value</td></tr></table>'
            . '<h1>PHP Credits</h1><table><tr><td>PHP Group</td><td>Contributors</td></tr></table>'
            . '<h2>PHP License</h2><table><tr><td>License text</td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput($body, 'x', 'cli', 'Linux', '');
        $titles = [];

        foreach ($view->tocEntries as $entry) {
            $titles[] = $entry->title;
        }

        self::assertSame(
            ['Overview', 'PHP Variables', 'PHP Credits', 'PHP License'],
            $titles,
            'The h1-based PHP Credits block must become an independent module instead of extending PHP Variables.',
        );
        self::assertStringContainsString(
            'id="phpinfo-php-credits"',
            $view->modulesHtml,
            'PHP Credits must expose its own deep-linkable section.',
        );
    }

    public function testFromOutputShortenPathsAgainstHomeDirectory(): void
    {
        $body = '<table><tr><td>Loaded Configuration File</td><td>/home/dev/projects/app/php.ini</td></tr></table>';
        $_SERVER['HOME'] = '/home/dev';

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        $tile = $this->findTileByLabel($view, 'Loaded Configuration File');

        unset($_SERVER['HOME']);

        self::assertNotNull(
            $tile,
            'Loaded Configuration File tile must surface.',
        );
        self::assertSame(
            '~/projects/app/php.ini',
            $tile->displayValue,
            "Paths under the resolved home directory must be shortened to '~/...'.",
        );
        self::assertSame(
            '/home/dev/projects/app/php.ini',
            $tile->rawValue,
            'Raw path must be preserved alongside the shortened display value.',
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

    public function testFromOutputSurfacesPathTokensForStandaloneAbsolutePath(): void
    {
        $body = '<table><tr><td>Loaded Configuration File</td><td>/etc/php/cli/php.ini</td></tr></table>';

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            'x',
            'cli',
            'Linux',
            '',
        );

        $tile = $this->findTileByLabel($view, 'Loaded Configuration File');

        self::assertNotNull(
            $tile,
            'Loaded Configuration File tile must surface.',
        );
        self::assertSame(
            PhpInfoTile::KIND_PATH,
            $tile->kind,
            "Single leading '/' path must classify as 'KIND_PATH'.",
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

    public function testResolveHomeDirectoryReturnsEmptyWhenEnvAndPosixUnavailable(): void
    {
        unset($_SERVER['HOME'], $_SERVER['USERPROFILE']);
        putenv('HOME');
        putenv('USERPROFILE');

        MockerState::addCondition(
            'yii\debug\widgets\phpinfo',
            'function_exists',
            [],
            false,
            true,
        );

        $view = PhpInfoDataNormalizer::fromOutput(
            '<table><tr><td>Loaded Configuration File</td><td>/etc/php.ini</td></tr></table>',
            'x',
            'cli',
            'Linux',
            '',
        );

        $tile = $this->findTileByLabel($view, 'Loaded Configuration File');

        self::assertNotNull(
            $tile,
            'Loaded Configuration File tile must surface.',
        );
        self::assertSame(
            '/etc/php.ini',
            $tile->displayValue,
            "With no home directory resolved, paths must surface verbatim (empty '\$home' skips shortening).",
        );
    }

    private function findTileByLabel(PhpInfoView $view, string $label): PhpInfoTile|null
    {
        foreach ($view->sections as $section) {
            foreach ($section->tiles as $tile) {
                if ($tile->label === $label) {
                    return $tile;
                }
            }
        }

        return null;
    }
}
