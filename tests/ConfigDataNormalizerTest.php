<?php

declare(strict_types=1);

namespace yiiunit\debug;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\config\ConfigDataNormalizer;

/**
 * Unit tests for {@see ConfigDataNormalizer} covering payload narrowing, missing keys, scalar coercion of the
 * application/php blocks and pass-through of the typed extensions roster.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('panel')]
#[Group('config')]
final class ConfigDataNormalizerTest extends TestCase
{
    public function testNormalizeCoercesBooleanFieldsViaCast(): void
    {
        $summary = (new ConfigDataNormalizer())->normalize(
            [
                'application' => ['debug' => 1],
                'php' => [
                    'xdebug' => 0,
                    'apcu' => 'truthy',
                    'memcache' => '',
                    'memcached' => null,
                ],
            ],
            [],
        );

        self::assertTrue(
            $summary->application->debug,
            "Truthy 'debug' must coerce to 'true'.",
        );
        self::assertFalse(
            $summary->php->xdebug,
            "'0' must coerce to 'false'.",
        );
        self::assertTrue(
            $summary->php->apcu,
            "Non-empty string must coerce to 'true'.",
        );
        self::assertFalse(
            $summary->php->memcache,
            "Empty string must coerce to 'false'.",
        );
        self::assertFalse(
            $summary->php->memcached,
            "null must coerce to 'false'.",
        );
    }

    public function testNormalizeFallsBackToDefaultsWhenApplicationOrPhpAreNotArrays(): void
    {
        $summary = (new ConfigDataNormalizer())->normalize(
            [
                'application' => 'invalid',
                'php' => 42,
            ],
            [],
        );

        self::assertSame(
            '',
            $summary->application->yii,
            "Invalid 'application' must collapse to defaults.",
        );
        self::assertFalse(
            $summary->application->debug,
            "Invalid 'application' must keep 'debug = false'.",
        );
        self::assertSame(
            '',
            $summary->php->version,
            "Invalid 'php' must collapse to defaults.",
        );
        self::assertFalse(
            $summary->php->xdebug,
            "Invalid 'php' must keep 'xdebug = false'.",
        );
    }

    public function testNormalizeFallsBackToEmptyStringWhenScalarFieldsAreNotStrings(): void
    {
        $summary = (new ConfigDataNormalizer())->normalize(
            [
                'application' => [
                    'name' => 42,
                    'env' => null,
                    'language' => false,
                ],
                'php' => ['version' => 7.4],
            ],
            [],
        );

        self::assertSame(
            '',
            $summary->application->name,
            "Non-string 'name' must collapse to ''.",
        );
        self::assertSame(
            '',
            $summary->application->env,
            "Non-string 'env' must collapse to ''.",
        );
        self::assertSame(
            '',
            $summary->application->language,
            "Non-string 'language' must collapse to ''.",
        );
        self::assertSame(
            '',
            $summary->php->version,
            "Non-string 'php.version' must collapse to ''.",
        );
    }

    public function testNormalizeFillsApplicationBlockFromTypedPayload(): void
    {
        $summary = (new ConfigDataNormalizer())->normalize(
            [
                'application' => [
                    'yii' => '2.0.50',
                    'name' => 'Demo',
                    'version' => '1.2.3',
                    'language' => 'en-US',
                    'sourceLanguage' => 'en',
                    'charset' => 'UTF-8',
                    'env' => 'prod',
                    'debug' => true,
                ],
            ],
            [],
        );

        $app = $summary->application;

        self::assertSame(
            '2.0.50',
            $app->yii,
            'Yii version must round-trip.',
        );
        self::assertSame(
            'Demo',
            $app->name,
            'Application name must round-trip.',
        );
        self::assertSame(
            '1.2.3',
            $app->version,
            'Application version must round-trip.',
        );
        self::assertSame(
            'en-US',
            $app->language,
            'Active language must round-trip.',
        );
        self::assertSame(
            'en',
            $app->sourceLanguage,
            'Source language must round-trip.',
        );
        self::assertSame(
            'UTF-8',
            $app->charset,
            'Charset must round-trip.',
        );
        self::assertSame(
            'prod',
            $app->env,
            'Environment label must round-trip.',
        );
        self::assertTrue(
            $app->debug,
            'Debug flag must round-trip.',
        );
    }

    public function testNormalizeFillsPhpBlockFromTypedPayload(): void
    {
        $summary = (new ConfigDataNormalizer())->normalize(
            [
                'php' => [
                    'version' => '8.3.10',
                    'xdebug' => true,
                    'apcu' => false,
                    'memcache' => true,
                    'memcached' => false,
                ],
            ],
            [],
        );

        $php = $summary->php;

        self::assertSame(
            '8.3.10',
            $php->version,
            'PHP version must round-trip.',
        );
        self::assertTrue(
            $php->xdebug,
            'Xdebug flag must round-trip.',
        );
        self::assertFalse(
            $php->apcu,
            'APCu flag must round-trip.',
        );
        self::assertTrue(
            $php->memcache,
            'Memcache flag must round-trip.',
        );
        self::assertFalse(
            $php->memcached,
            'Memcached flag must round-trip.',
        );
    }

    public function testNormalizePassesThroughExtensionsRoster(): void
    {
        $extensions = [
            'acme/foo' => '1.0.0',
            'acme/bar' => '2.5.1',
        ];

        $summary = (new ConfigDataNormalizer())->normalize(
            [],
            $extensions,
        );

        self::assertSame(
            $extensions,
            $summary->extensions,
            'Extensions roster must round-trip unmodified.',
        );
        self::assertTrue(
            $summary->hasExtensions(),
            "Non-empty roster must report 'hasExtensions = true'.",
        );
        self::assertSame(
            2,
            $summary->extensionCount(),
            'Extension count must reflect the roster size.',
        );
    }

    public function testNormalizeReportsEmptyExtensionsCorrectly(): void
    {
        $summary = (new ConfigDataNormalizer())->normalize(
            [],
            [],
        );

        self::assertFalse(
            $summary->hasExtensions(),
            "Empty roster must report 'hasExtensions = false'.",
        );
        self::assertSame(
            0,
            $summary->extensionCount(),
            "Empty roster must report 'extensionCount = 0'.",
        );
    }

    public function testNormalizeReturnsEmptyDefaultsWhenDataIsNotArray(): void
    {
        $summary = (new ConfigDataNormalizer())->normalize(
            'not an array',
            [],
        );

        self::assertSame(
            '',
            $summary->application->yii,
            "Missing 'yii' must collapse to ''.",
        );
        self::assertSame(
            '',
            $summary->application->name,
            "Missing 'name' must collapse to ''.",
        );
        self::assertSame(
            '',
            $summary->php->version,
            "Missing 'php.version' must collapse to ''.",
        );
        self::assertFalse(
            $summary->application->debug,
            "Missing 'debug' must collapse to 'false'.",
        );
        self::assertFalse(
            $summary->php->xdebug,
            "Missing 'xdebug' must collapse to 'false'.",
        );
        self::assertSame(
            [],
            $summary->extensions,
            'Empty extensions roster must round-trip.',
        );
    }
}
