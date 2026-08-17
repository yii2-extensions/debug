<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\debug\collectors\ConfigCollector;
use yii\debug\tests\support\TestCase;

use function is_string;

/**
 * Unit tests for {@see ConfigCollector} covering the configuration snapshot, the application resolution fallback, the
 * extension-roster narrowing, and the startup/shutdown lifecycle.
 */
#[Group('collector')]
#[Group('config')]
final class ConfigCollectorTest extends TestCase
{
    public function testCaptureCollapsesApplicationFieldsWhenYiiAppIsNotApplication(): void
    {
        $collector = $this->makeCollector();

        $payload = $this->captureData($collector);

        $application = $payload['application'] ?? null;

        self::assertIsArray(
            $application,
            'Application slice must be an array.',
        );
        self::assertSame(
            [
                'yii' => $payload['yiiVersion'] ?? null,
                'name' => '',
                'version' => '',
                'language' => '',
                'sourceLanguage' => '',
                'charset' => '',
                'env' => YII_ENV,
                'debug' => YII_DEBUG,
            ],
            $application,
            'Missing application must retain the complete neutral application schema.',
        );
        self::assertSame(
            [],
            $payload['extensions'] ?? null,
            "Missing application must collapse extensions to '[]'.",
        );
    }
    public function testCaptureReturnsNullAfterShutdown(): void
    {
        $collector = $this->makeCollector();

        $collector->shutdown();

        self::assertNull(
            $collector->capture(),
            'Stopped collector must record nothing.',
        );
    }

    public function testCaptureReturnsNullBeforeStartup(): void
    {
        self::assertNull(
            (new ConfigCollector())->capture(),
            'Idle collector must record nothing.',
        );
    }

    public function testCaptureSnapshotsTheActiveApplication(): void
    {
        $this->mockWebApplication(
            [
                'name' => 'TestApp',
                'language' => 'es-ES',
                'sourceLanguage' => 'es',
                'charset' => 'UTF-8',
            ],
        );

        $collector = $this->makeCollector();

        $payload = $this->captureData($collector);

        $application = $payload['application'] ?? null;

        self::assertIsArray(
            $application,
            'Application slice must be an array.',
        );
        self::assertSame(
            PHP_VERSION,
            $payload['phpVersion'] ?? null,
            'PHP version must match the runtime constant.',
        );
        self::assertSame(
            'TestApp',
            $application['name'] ?? null,
            'Application name must round-trip.',
        );
        self::assertSame(
            'es-ES',
            $application['language'] ?? null,
            'Application language must round-trip.',
        );
        self::assertSame(
            YII_ENV,
            $application['env'] ?? null,
            "'env' must match the 'YII_ENV' constant.",
        );
        self::assertSame(
            YII_DEBUG,
            $application['debug'] ?? null,
            "'debug' must match the 'YII_DEBUG' constant.",
        );
        self::assertSame(
            [
                'version' => PHP_VERSION,
                'xdebug' => extension_loaded('xdebug'),
                'apcu' => extension_loaded('apcu'),
                'memcache' => extension_loaded('memcache'),
                'memcached' => extension_loaded('memcached'),
            ],
            $payload['php'] ?? null,
            'The PHP capability slice must retain every captured field.',
        );
    }

    public function testGetApplicationReturnsNullWhenYiiAppIsUnset(): void
    {
        $collector = $this->makeCollector();

        self::assertNull(
            $this->invoke(
                $collector,
                'getApplication',
            ),
            "Missing 'Yii::\$app' must collapse to 'null'.",
        );
    }

    public function testGetApplicationReturnsTheActiveWebApplication(): void
    {
        $this->mockWebApplication();

        $collector = $this->makeCollector();

        self::assertSame(
            Yii::$app,
            $this->invoke(
                $collector,
                'getApplication',
            ),
            "Resolved application must match the active 'Yii::\$app'.",
        );
    }

    public function testIdPairsWithTheConfigurationPanel(): void
    {
        self::assertSame(
            'config',
            (new ConfigCollector())->id(),
            "Stable ID must be 'config'.",
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
                'acme/baz' => ['name' => 'acme/baz', 'version' => '2.0'],
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
        self::assertArrayHasKey(
            'acme/baz',
            $normalized,
            'A malformed entry must not stop normalization of later extensions.',
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

    /**
     * Extracts the captured payload, failing when the started collector produces no snapshot.
     *
     * @param ConfigCollector $collector Started collector.
     *
     * @return array<array-key, mixed> Captured payload.
     */
    private function captureData(ConfigCollector $collector): array
    {
        $snapshot = $collector->capture();

        self::assertNotNull($snapshot, 'Started collector must capture a snapshot.');

        return $snapshot->data();
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
     * Creates a started collector.
     *
     * @return ConfigCollector Started collector.
     */
    private function makeCollector(): ConfigCollector
    {
        $collector = new ConfigCollector();

        $collector->startup();

        return $collector;
    }

    /**
     * Runs {@see ConfigCollector::normalizeExtensions()} via reflection and narrows the result for downstream typed access.
     *
     * @param array<int|string, mixed> $input
     *
     * @return array<int|string, array<string, mixed>>
     */
    private function normalize(array $input): array
    {
        $result = $this->invoke(
            new ConfigCollector(),
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
