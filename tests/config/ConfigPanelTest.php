<?php

declare(strict_types=1);

namespace yii\debug\tests\config;

use PHPForge\Debug\Panel\Config\ConfigSnapshot;
use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\ConfigPanel;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see ConfigPanel} covering the extension roster narrowing, the version pluck helpers, and the toolbar-items short-circuit.
 */
#[Group('panel')]
#[Group('config')]
final class ConfigPanelTest extends TestCase
{
    public function testGetDetailRendersWithCapturedSnapshot(): void
    {
        $panel = $this->makePanel(ConfigPanel::class);

        $this->hydratePanel(
            $panel,
            ConfigSnapshot::capture(
                [
                    'phpVersion' => '8.3.10',
                    'yiiVersion' => '2.0.50',
                    'application' => [
                        'yii' => '2.0.50',
                        'name' => 'Demo',
                        'version' => '1.0.0',
                        'language' => 'en-US',
                        'sourceLanguage' => 'en',
                        'charset' => 'UTF-8',
                        'env' => 'dev',
                        'debug' => true,
                    ],
                    'php' => [
                        'version' => '8.3.10',
                        'xdebug' => false,
                        'apcu' => false,
                        'memcache' => false,
                        'memcached' => false,
                    ],
                    'extensions' => [
                        ['name' => 'acme/foo', 'version' => '1.0.0'],
                    ],
                ],
            ),
        );

        $html = $panel->getDetail();

        self::assertNotEmpty(
            $html,
            'Detail view must produce non-empty markup.',
        );
    }

    public function testGetExtensionsCoercesScalarVersionsAndSortsByName(): void
    {
        $panel = new ConfigPanel();

        $this->hydratePanel(
            $panel,
            ConfigSnapshot::capture(
                [
                    'extensions' => [
                        ['name' => 'acme/zebra', 'version' => '1.0.0'],
                        ['name' => 'acme/apple', 'version' => '2.5.1'],
                    ],
                ],
            ),
        );

        self::assertSame(
            ['acme/apple' => '2.5.1', 'acme/zebra' => '1.0.0'],
            $panel->getExtensions(),
            'Extensions roster must be sorted alphabetically by name.',
        );
    }

    public function testGetExtensionsDropsEntriesThatAreNotExtensionDescriptors(): void
    {
        $panel = $this->makePanel(ConfigPanel::class);

        $this->hydratePanel(
            $panel,
            ConfigSnapshot::capture(
                [
                    'extensions' => [
                        ['name' => 'vendor/package', 'version' => '1.0.0'],
                        'not-a-descriptor',
                        ['name' => 'vendor/after', 'version' => '2.0.0'],
                    ],
                ],
            ),
        );

        self::assertSame(
            ['vendor/after' => '2.0.0', 'vendor/package' => '1.0.0'],
            $panel->getExtensions(),
            'Malformed descriptors must not stop later valid extensions from being collected.',
        );
    }

    public function testGetExtensionsReturnsEmptyWhenSnapshotIsMissing(): void
    {
        $panel = new ConfigPanel();

        self::assertSame(
            [],
            $panel->getExtensions(),
            'Non-array data must yield an empty roster.',
        );
    }

    public function testGetExtensionsSkipsEntriesWithNonStringNameOrVersion(): void
    {
        $panel = new ConfigPanel();

        $this->hydratePanel(
            $panel,
            ConfigSnapshot::capture(
                [
                    'extensions' => [
                        ['name' => 'acme/foo', 'version' => '1.0.0'],
                        ['name' => 42, 'version' => '2.0.0'],
                        ['name' => 'acme/bar', 'version' => null],
                        ['version' => 'orphan'],
                    ],
                ],
            ),
        );

        self::assertSame(
            ['acme/foo' => '1.0.0'],
            $panel->getExtensions(),
            "Only entries with string 'name' and 'version' must round-trip.",
        );
    }

    public function testGetNameAndIcon(): void
    {
        $panel = new ConfigPanel();

        self::assertSame(
            'Configuration',
            $panel->getName(),
            "Display name must be 'Configuration'.",
        );
        self::assertSame(
            'config',
            $panel->getToolbarIcon(),
            "Icon key must be 'config'.",
        );
    }

    public function testGetPhpVersionReturnsNullWhenDataIsMissing(): void
    {
        $panel = new ConfigPanel();

        self::assertNull(
            $panel->getPhpVersion(),
            "Missing snapshot must collapse to 'null'.",
        );
    }

    public function testGetPhpVersionReturnsNullWhenInnerKeyIsNotScalar(): void
    {
        $panel = new ConfigPanel();

        $this->hydratePanel(
            $panel,
            ConfigSnapshot::capture(['php' => ['version' => ['nested']]]),
        );

        self::assertNull(
            $panel->getPhpVersion(),
            "Non-scalar 'php.version' must collapse to 'null'.",
        );
    }

    public function testGetPhpVersionReturnsNullWhenOuterIsNotArray(): void
    {
        $panel = new ConfigPanel();

        $this->hydratePanel(
            $panel,
            ConfigSnapshot::capture(['php' => 'not an array']),
        );

        self::assertNull(
            $panel->getPhpVersion(),
            "Non-array 'php' slice must collapse to 'null'.",
        );
    }

    public function testGetPhpVersionReturnsSavedScalar(): void
    {
        $panel = new ConfigPanel();

        $this->hydratePanel(
            $panel,
            ConfigSnapshot::capture(['php' => ['version' => '8.3.10']]),
        );

        self::assertSame(
            '8.3.10',
            $panel->getPhpVersion(),
            "Saved 'php.version' must round-trip.",
        );
    }

    public function testGetToolbarItemsAlwaysReturnsEmptyArray(): void
    {
        $panel = new ConfigPanel();

        self::assertSame(
            [],
            $this->invoke(
                $panel,
                'getToolbarItems',
            ),
            'Config panel must suppress its own toolbar items.',
        );
    }

    public function testGetYiiVersionReturnsNullWhenDataIsMissing(): void
    {
        $panel = new ConfigPanel();

        self::assertNull(
            $panel->getYiiVersion(),
            "Missing snapshot must collapse to 'null'.",
        );
    }

    public function testGetYiiVersionReturnsSavedScalar(): void
    {
        $panel = new ConfigPanel();

        $this->hydratePanel(
            $panel,
            ConfigSnapshot::capture(['application' => ['yii' => '2.0.50']]),
        );

        self::assertSame(
            '2.0.50',
            $panel->getYiiVersion(),
            "Saved 'application.yii' must round-trip.",
        );
    }
}
