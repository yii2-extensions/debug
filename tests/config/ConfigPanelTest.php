<?php

declare(strict_types=1);

namespace yii\debug\tests\config;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\debug\panels\ConfigPanel;
use yii\debug\tests\support\TestCase;

use function is_string;

/**
 * Unit tests for {@see ConfigPanel} covering the configuration snapshot produced by `save()`, the extension roster
 * narrowing, the version pluck helpers, the toolbar-items short-circuit, and the typed phpinfo wrapper.
 */
#[Group('panel')]
#[Group('config')]
final class ConfigPanelTest extends TestCase
{
    public function testGetApplicationReturnsNullWhenYiiAppIsUnset(): void
    {
        $panel = new ConfigPanel();

        self::assertNull(
            $this->invoke(
                $panel,
                'getApplication',
            ),
            "Missing 'Yii::\$app' must collapse to 'null'.",
        );
    }

    public function testGetApplicationReturnsTheActiveWebApplication(): void
    {
        $this->mockWebApplication();

        $panel = new ConfigPanel();

        self::assertSame(
            Yii::$app,
            $this->invoke(
                $panel,
                'getApplication',
            ),
            "Resolved application must match the active 'Yii::\$app'.",
        );
    }

    public function testGetDetailRendersWithCapturedSnapshot(): void
    {
        $panel = $this->makePanel(ConfigPanel::class);

        $panel->data = [
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
        ];

        $html = $panel->getDetail();

        self::assertNotEmpty(
            $html,
            'Detail view must produce non-empty markup.',
        );
    }

    public function testGetExtensionsCoercesScalarVersionsAndSortsByName(): void
    {
        $panel = new ConfigPanel();

        $panel->data = [
            'extensions' => [
                ['name' => 'acme/zebra', 'version' => '1.0.0'],
                ['name' => 'acme/apple', 'version' => '2.5.1'],
            ],
        ];

        self::assertSame(
            ['acme/apple' => '2.5.1', 'acme/zebra' => '1.0.0'],
            $panel->getExtensions(),
            'Extensions roster must be sorted alphabetically by name.',
        );
    }

    public function testGetExtensionsReturnsEmptyWhenDataIsNotArray(): void
    {
        $panel = new ConfigPanel();

        $this->setInaccessibleProperty(
            $panel,
            'data',
            'corrupt',
        );

        self::assertSame(
            [],
            $panel->getExtensions(),
            'Non-array data must yield an empty roster.',
        );
    }

    public function testGetExtensionsSkipsEntriesWithNonStringNameOrVersion(): void
    {
        $panel = new ConfigPanel();

        $this->setInaccessibleProperty(
            $panel,
            'data',
            [
                'extensions' => [
                    ['name' => 'acme/foo', 'version' => '1.0.0'],
                    ['name' => 42, 'version' => '2.0.0'],
                    ['name' => 'acme/bar', 'version' => null],
                    ['version' => 'orphan'],
                ],
            ],
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

    public function testGetPhpInfoReturnsCapturedOutput(): void
    {
        $panel = new ConfigPanel();

        $html = $panel->getPhpInfo();

        self::assertNotEmpty(
            $html,
            "Must capture 'phpinfo()' output.",
        );
        self::assertStringContainsString(
            'PHP',
            $html,
            "Captured output must mention 'PHP' (SAPI-agnostic anchor).",
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

        $this->setInaccessibleProperty(
            $panel,
            'data',
            ['php' => ['version' => ['nested']]],
        );

        self::assertNull(
            $panel->getPhpVersion(),
            "Non-scalar 'php.version' must collapse to 'null'.",
        );
    }

    public function testGetPhpVersionReturnsNullWhenOuterIsNotArray(): void
    {
        $panel = new ConfigPanel();

        $this->setInaccessibleProperty(
            $panel,
            'data',
            ['php' => 'not an array'],
        );

        self::assertNull(
            $panel->getPhpVersion(),
            "Non-array 'php' slice must collapse to 'null'.",
        );
    }

    public function testGetPhpVersionReturnsSavedScalar(): void
    {
        $panel = new ConfigPanel();

        $panel->data = ['php' => ['version' => '8.3.10']];

        self::assertSame(
            '8.3.10',
            $panel->getPhpVersion(),
            "Saved 'php.version' must round-trip.",
        );
    }

    public function testGetSummaryNameDelegatesToControllerTemplate(): void
    {
        $panel = $this->makePanel(ConfigPanel::class);

        $html = $panel->getSummary();

        self::assertNotEmpty(
            $html,
            'Summary chip must produce non-empty markup.',
        );
    }

    public function testGetToolbarItemsAlwaysReturnsNull(): void
    {
        $panel = new ConfigPanel();

        self::assertNull(
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

        $panel->data = [
            'application' => ['yii' => '2.0.50'],
        ];

        self::assertSame(
            '2.0.50',
            $panel->getYiiVersion(),
            "Saved 'application.yii' must round-trip.",
        );
    }

    public function testNormalizeExtensionsAcceptsBootstrapAsStringOrArray(): void
    {
        $normalized = $this->normalize(
            [
                'acme/foo' => ['name' => 'acme/foo', 'bootstrap' => 'app\\FooBootstrap'],
                'acme/bar' => ['name' => 'acme/bar', 'bootstrap' => ['class' => 'app\\BarBootstrap', 0 => 'dropped']],
            ],
        );

        $foo = $this->entry($normalized, 'acme/foo');
        $bar = $this->entry($normalized, 'acme/bar');

        self::assertSame(
            'app\\FooBootstrap',
            $foo['bootstrap'] ?? null,
            'String bootstrap must round-trip verbatim.',
        );
        self::assertSame(
            ['class' => 'app\\BarBootstrap'],
            $bar['bootstrap'] ?? null,
            'Array bootstrap must keep only string-keyed entries.',
        );
    }

    public function testNormalizeExtensionsDropsBootstrapWithUnsupportedType(): void
    {
        $normalized = $this->normalize(
            ['acme/foo' => ['name' => 'acme/foo', 'bootstrap' => 42]],
        );

        self::assertArrayNotHasKey(
            'bootstrap',
            $this->entry($normalized, 'acme/foo'),
            'Non-string and non-array bootstrap must be dropped.',
        );
    }

    public function testNormalizeExtensionsDropsMalformedAliasValues(): void
    {
        $normalized = $this->normalize(
            [
                'acme/foo' => [
                    'name' => 'acme/foo',
                    'alias' => [
                        '@valid' => '/path',
                        42 => '/numeric-key',
                        '@bad-value' => 123,
                    ],
                ],
            ],
        );

        self::assertSame(
            ['@valid' => '/path'],
            $this->entry($normalized, 'acme/foo')['alias'] ?? null,
            'Only string-keyed string-valued aliases must round-trip.',
        );
    }

    public function testNormalizeExtensionsSkipsNonArrayEntries(): void
    {
        $normalized = $this->normalize(
            [
                'acme/foo' => ['name' => 'acme/foo', 'version' => '1.0'],
                'acme/bar' => 'invalid',
            ],
        );

        self::assertArrayHasKey(
            'acme/foo',
            $normalized,
            'Array entries must survive.',
        );
        self::assertArrayNotHasKey(
            'acme/bar',
            $normalized,
            'Non-array entries must be dropped.',
        );
    }

    public function testNormalizeExtensionsSkipsNonStringNameAndVersion(): void
    {
        $normalized = $this->normalize(
            ['acme/foo' => ['name' => 42, 'version' => null]],
        );

        $entry = $this->entry($normalized, 'acme/foo');

        self::assertArrayNotHasKey(
            'name',
            $entry,
            "Non-string 'name' must be dropped.",
        );
        self::assertArrayNotHasKey(
            'version',
            $entry,
            "Non-string 'version' must be dropped.",
        );
    }

    public function testSaveCollapsesApplicationFieldsWhenYiiAppIsNotApplication(): void
    {
        $panel = new ConfigPanel();

        $payload = $panel->save();

        self::assertSame(
            '',
            $payload['application']['name'],
            "Missing application must collapse 'name' to ''.",
        );
        self::assertSame(
            [],
            $payload['extensions'],
            "Missing application must collapse extensions to '[]'.",
        );
    }

    public function testSaveSnapshotsTheActiveApplication(): void
    {
        $this->mockWebApplication(
            [
                'name' => 'TestApp',
                'language' => 'es-ES',
                'sourceLanguage' => 'es',
                'charset' => 'UTF-8',
            ],
        );

        $panel = new ConfigPanel();

        $payload = $panel->save();

        self::assertSame(
            PHP_VERSION,
            $payload['phpVersion'],
            'PHP version must match the runtime constant.',
        );
        self::assertSame(
            'TestApp',
            $payload['application']['name'],
            'Application name must round-trip.',
        );
        self::assertSame(
            'es-ES',
            $payload['application']['language'],
            'Application language must round-trip.',
        );
        self::assertSame(
            YII_ENV,
            $payload['application']['env'],
            "'env' must match the 'YII_ENV' constant.",
        );
        self::assertSame(
            YII_DEBUG,
            $payload['application']['debug'],
            "'debug' must match the 'YII_DEBUG' constant.",
        );
    }

    public function testStringKeyedArrayFiltersOutIntegerKeys(): void
    {
        $filtered = $this->invoke(
            new ConfigPanel(),
            'stringKeyedArray',
            [
                [
                    'kept' => 1,
                    42 => 'dropped',
                    'also-kept' => 2,
                ],
            ],
        );

        self::assertSame(
            ['kept' => 1, 'also-kept' => 2],
            $filtered,
            'Only string-keyed entries must survive.',
        );
    }

    /**
     * Returns a single normalized entry as an `array<string, mixed>` keyed by its package name.
     *
     * @param array<int|string, array<string, mixed>> $normalized Normalized extensions roster keyed by package name.
     *
     * @return array<string, mixed> Normalized entry keyed by string keys.
     */
    private function entry(array $normalized, string $name): array
    {
        return $normalized[$name] ?? self::fail("Expected entry '{$name}' to be present.");
    }

    /**
     * Runs {@see ConfigPanel::normalizeExtensions()} via reflection and narrows the result for downstream typed access.
     *
     * @param array<int|string, mixed> $input
     *
     * @return array<int|string, array<string, mixed>>
     */
    private function normalize(array $input): array
    {
        $result = $this->invoke(
            new ConfigPanel(),
            'normalizeExtensions',
            [$input],
        );

        self::assertIsArray(
            $result,
            'normalizeExtensions must produce an array.',
        );

        $out = [];

        foreach ($result as $key => $value) {
            self::assertIsArray(
                $value,
                'Normalized entry must be an array.',
            );

            $entry = [];

            foreach ($value as $k => $v) {
                if (is_string($k)) {
                    $entry[$k] = $v;
                }
            }

            $out[$key] = $entry;
        }

        return $out;
    }
}
