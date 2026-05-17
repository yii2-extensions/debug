<?php

declare(strict_types=1);

namespace yii\debug\tests\config;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\config\{ApplicationConfig, ConfigCardRenderer, ConfigSummary, PhpConfig};
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see ConfigCardRenderer} covering readout grid composition, PHP-extension pills, application
 * details rows, the conditional installed-extensions section and the php-info call-to-action link.
 */
#[Group('panel')]
#[Group('config')]
final class ConfigCardRendererTest extends TestCase
{
    public function testRenderApplicationDetailsSectionShowsCharsetAndLanguageRows(): void
    {
        $summary = self::makeSummary(charset: 'UTF-8', language: 'en-US', sourceLanguage: 'en');

        $html = ConfigCardRenderer::renderApplicationDetailsSection($summary->application)->render();

        self::assertStringContainsString(
            'Charset',
            $html,
            'Charset row must be labeled.',
        );
        self::assertStringContainsString(
            'UTF-8',
            $html,
            'Charset value must be rendered.'
        );
        self::assertStringContainsString(
            'Current language',
            $html,
            'Current language row must be labeled.'
        );
        self::assertStringContainsString(
            'en-US',
            $html,
            'Current language value must be rendered.'
        );
        self::assertStringContainsString(
            'Source language',
            $html,
            'Source language row must be labeled.'
        );
    }

    public function testRenderApplicationDetailsSectionShowsEmDashWhenCharsetIsEmpty(): void
    {
        $summary = self::makeSummary(charset: '', language: '', sourceLanguage: '');

        $html = ConfigCardRenderer::renderApplicationDetailsSection($summary->application)->render();

        self::assertStringContainsString(
            '—',
            $html,
            'Empty charset must render the em-dash placeholder.',
        );
    }

    public function testRenderInstalledExtensionsSectionListsEveryPackageWithVersionPrefix(): void
    {
        $summary = self::makeSummary(extensions: ['acme/foo' => '1.0.0', 'acme/bar' => '2.5.1']);

        $section = ConfigCardRenderer::renderInstalledExtensionsSection($summary);

        self::assertNotNull(
            $section,
            'Non-empty roster must produce a section.',
        );

        $html = $section->render();

        self::assertStringContainsString(
            'Installed extensions',
            $html,
            'Section heading must be present.',
        );
        self::assertStringContainsString(
            'acme/foo',
            $html,
            'First package name must be listed.',
        );
        self::assertStringContainsString(
            'v1.0.0',
            $html,
            "First package version must be prefixed with 'v'.",
        );
        self::assertStringContainsString(
            'acme/bar',
            $html,
            'Second package name must be listed.',
        );
        self::assertStringContainsString(
            'v2.5.1',
            $html,
            "Second package version must be prefixed with 'v'.",
        );
        self::assertStringContainsString(
            '>2<',
            $html,
            'Section count chip must render the roster size.',
        );
    }

    public function testRenderInstalledExtensionsSectionReturnsNullWhenRosterIsEmpty(): void
    {
        $summary = self::makeSummary(extensions: []);

        self::assertNull(
            ConfigCardRenderer::renderInstalledExtensionsSection($summary),
            "Empty roster must return 'null' so the caller can omit the section.",
        );
    }

    public function testRenderPhpExtensionsSectionEmitsOneOnAndThreeOffPills(): void
    {
        $summary = self::makeSummary(xdebug: true);

        $html = ConfigCardRenderer::renderPhpExtensionsSection($summary->php)->render();

        self::assertStringContainsString(
            'Xdebug',
            $html,
            'Xdebug label must be present.',
        );
        self::assertStringContainsString(
            'APCu',
            $html,
            'APCu label must be present.',
        );
        self::assertStringContainsString(
            'Memcache',
            $html,
            'Memcache label must be present.',
        );
        self::assertStringContainsString(
            'Memcached',
            $html,
            'Memcached label must be present.',
        );
        self::assertStringContainsString(
            'is-on',
            $html,
            "On state must use the 'is-on' modifier.",
        );
        self::assertStringContainsString(
            'is-off',
            $html,
            "Off state must use the 'is-off' modifier.",
        );
    }

    public function testRenderPhpInfoCtaProducesAnchorWithProvidedHref(): void
    {
        $html = ConfigCardRenderer::renderPhpInfoCta('/debug/default/php-info')->render();

        self::assertStringContainsString(
            'class="yii-debug-cta"',
            $html,
            'CTA must carry the wrapper class.',
        );
        self::assertStringContainsString(
            'href="/debug/default/php-info"',
            $html,
            'CTA href must round-trip the caller-provided URL.',
        );
        self::assertStringContainsString(
            'target="_blank"',
            $html,
            'CTA must open in a new tab.',
        );
        self::assertStringContainsString(
            'rel="noopener"',
            $html,
            "CTA must declare 'rel=\"noopener\"' for safety.",
        );
        self::assertStringContainsString(
            'View full phpinfo',
            $html,
            'CTA must show the descriptive label.',
        );
    }

    public function testRenderReadoutGridShowsDebugOffMutedChipWhenDebugIsFalse(): void
    {
        $summary = self::makeSummary(debug: false);

        $html = ConfigCardRenderer::renderReadoutGrid($summary)->render();

        self::assertStringContainsString(
            'yii-debug-readout-chip yii-debug-readout-chip-muted',
            $html,
            'Disabled chip must use the muted modifier.',
        );
        self::assertStringContainsString(
            'off',
            $html,
            "Disabled chip must read 'off'.",
        );
    }

    public function testRenderReadoutGridShowsDebugOnChipWhenDebugIsTrue(): void
    {
        $summary = self::makeSummary(debug: true);

        $html = ConfigCardRenderer::renderReadoutGrid($summary)->render();

        self::assertStringContainsString(
            'debug',
            $html,
            'Debug chip text must be present.',
        );
        self::assertStringContainsString(
            'on',
            $html,
            "Debug chip must read 'on' when debug is 'true'.",
        );
        self::assertStringNotContainsString(
            'yii-debug-readout-chip-muted">debug',
            $html,
            'Active chip must not be muted.',
        );
    }

    public function testRenderReadoutGridShowsEmDashWhenApplicationNameIsEmpty(): void
    {
        $summary = self::makeSummary(name: '');

        $html = ConfigCardRenderer::renderReadoutGrid($summary)->render();

        self::assertStringContainsString(
            '—',
            $html,
            "Empty 'name' must render the em-dash placeholder.",
        );
    }

    public function testRenderReadoutGridShowsInstanceFallbackWhenApplicationVersionIsEmpty(): void
    {
        $summary = self::makeSummary(version: '');

        $html = ConfigCardRenderer::renderReadoutGrid($summary)->render();

        self::assertStringContainsString(
            'instance',
            $html,
            "Empty 'version' must fall back to 'instance' text.",
        );
    }

    public function testRenderReadoutGridShowsVersionChipWhenApplicationVersionIsPresent(): void
    {
        $summary = self::makeSummary(version: '1.2.3');

        $html = ConfigCardRenderer::renderReadoutGrid($summary)->render();

        self::assertStringContainsString(
            'v1.2.3',
            $html,
            'Version chip must show the prefixed application version.',
        );
        self::assertStringNotContainsString(
            '>instance<',
            $html,
            "Version chip must replace the 'instance' fallback.",
        );
    }

    public function testRenderReadoutGridShowsYiiAndPhpAndEnvironmentAndApplicationCards(): void
    {
        $summary = self::makeSummary(yii: '2.0.50', phpVersion: '8.3.10', env: 'prod', name: 'Demo');

        $html = ConfigCardRenderer::renderReadoutGrid($summary)->render();

        self::assertStringContainsString(
            'class="yii-debug-readout"',
            $html,
            'Outer wrapper class must be present.',
        );
        self::assertStringContainsString(
            'Yii',
            $html,
            'Yii readout label must be present.',
        );
        self::assertStringContainsString(
            '2.0.50',
            $html,
            'Yii readout value must be present.',
        );
        self::assertStringContainsString(
            'PHP',
            $html,
            'PHP readout label must be present.',
        );
        self::assertStringContainsString(
            '8.3.10',
            $html,
            'PHP readout value must be present.',
        );
        self::assertStringContainsString(
            'Environment',
            $html,
            'Environment readout label must be present.',
        );
        self::assertStringContainsString(
            'Application',
            $html,
            'Application readout label must be present.',
        );
        self::assertStringContainsString(
            'Demo',
            $html,
            'Application readout value must be present.',
        );
    }

    /**
     * @param array<string, string> $extensions
     */
    private static function makeSummary(
        string $yii = '2.0.0',
        string $phpVersion = '8.3.0',
        string $name = 'Test',
        string $version = '',
        string $language = '',
        string $sourceLanguage = '',
        string $charset = '',
        string $env = 'dev',
        bool $debug = true,
        bool $xdebug = false,
        bool $apcu = false,
        bool $memcache = false,
        bool $memcached = false,
        array $extensions = [],
    ): ConfigSummary {
        return new ConfigSummary(
            application: new ApplicationConfig(
                yii: $yii,
                name: $name,
                version: $version,
                language: $language,
                sourceLanguage: $sourceLanguage,
                charset: $charset,
                env: $env,
                debug: $debug,
            ),
            php: new PhpConfig(
                version: $phpVersion,
                xdebug: $xdebug,
                apcu: $apcu,
                memcache: $memcache,
                memcached: $memcached,
            ),
            extensions: $extensions,
        );
    }
}
