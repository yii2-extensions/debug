<?php

declare(strict_types=1);

namespace yii\debug\tests;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\tests\support\TestCase;
use yii\debug\VersionResolver;

/**
 * Unit tests for {@see VersionResolver}, the static helper that produces human-readable Composer
 * package versions for the debug UI.
 */
#[Group('version-resolver')]
final class VersionResolverTest extends TestCase
{
    public function testForExtensionsLeavesUnresolvableEntriesUntouched(): void
    {
        $resolved = VersionResolver::forExtensions(
            [
                'vendor/missing-extension' => [
                    'name' => 'vendor/missing-extension',
                    'version' => 'dev-master',
                ],
            ],
        );

        self::assertArrayHasKey(
            'vendor/missing-extension',
            $resolved,
            'Resolved map must keep the input keys.',
        );
        self::assertArrayHasKey(
            'version',
            $resolved['vendor/missing-extension'],
            "Entry must retain its 'version' key.",
        );
        self::assertSame(
            'dev-master',
            $resolved['vendor/missing-extension']['version'],
            "Unresolvable entries must keep their original 'version' to avoid data loss.",
        );
    }

    public function testForExtensionsReplacesVersionFieldWhenResolvable(): void
    {
        $resolved = VersionResolver::forExtensions(
            [
                'yiisoft/yii2-symfonymailer' => [
                    'name' => 'yiisoft/yii2-symfonymailer',
                    'version' => '99.99.99',
                ],
            ],
        );

        self::assertArrayHasKey(
            'yiisoft/yii2-symfonymailer',
            $resolved,
            'Resolved map must keep the input keys.',
        );
        self::assertArrayHasKey(
            'version',
            $resolved['yiisoft/yii2-symfonymailer'],
            "Entry must retain its 'version' key.",
        );
        self::assertNotSame(
            '99.99.99',
            $resolved['yiisoft/yii2-symfonymailer']['version'],
            "Resolvable extensions must have their 'version' field rewritten to the friendly form.",
        );
    }

    public function testForExtensionsSkipsNonStringKeysWithoutCrashing(): void
    {
        $resolved = VersionResolver::forExtensions(
            [
                ['name' => 'broken', 'version' => 'dev-master'],
            ]
        );

        self::assertArrayHasKey(
            0,
            $resolved,
            'Numeric-keyed entries must survive the pass-through.',
        );
        self::assertArrayHasKey(
            'version',
            $resolved[0],
            "Entry must retain its 'version' key.",
        );
        self::assertSame(
            'dev-master',
            $resolved[0]['version'],
            'Numeric keys come from malformed registrations and must pass through untouched.',
        );
    }

    public function testForPackageAppendsShortGitReferenceForDevBranches(): void
    {
        $version = VersionResolver::forPackage(
            'yiisoft/yii2',
        );

        self::assertNotNull(
            $version,
            'Framework version must resolve in a dev workspace.',
        );
        self::assertMatchesRegularExpression(
            '/(?:dev|x-dev|dev-[\w.\-]+).* @[0-9a-f]{7}$/',
            $version,
            "Dev branches must end with ' @<7-char SHA>' for build identification.",
        );
    }

    public function testForPackageReturnsNullForUnknownPackage(): void
    {
        self::assertNull(
            VersionResolver::forPackage('vendor/this-package-does-not-exist'),
            "Unknown packages must resolve to 'null' so callers can fall back gracefully.",
        );
    }

    public function testForPackageReturnsPrettyVersionForInstalledFrameworkPackage(): void
    {
        $version = VersionResolver::forPackage(
            'yiisoft/yii2',
        );

        self::assertNotNull(
            $version,
            "'yiisoft/yii2' is a hard dependency, so a friendly version must resolve.",
        );
        self::assertStringNotContainsString(
            '9999999',
            $version,
            'Synthetic infinity version must not leak into the rendered string.',
        );
    }

    public function testForPackageReturnsTaggedVersionVerbatimForStablePackages(): void
    {
        $version = VersionResolver::forPackage('cebe/markdown');

        self::assertNotNull(
            $version,
            "'cebe/markdown' is installed as a tagged release.",
        );
        self::assertStringNotContainsString(
            ' @',
            $version,
            'Tagged releases must not carry the dev SHA suffix.',
        );
        self::assertMatchesRegularExpression(
            '/^\d+\.\d+\.\d+$/',
            $version,
            'Tagged releases must surface as plain semver strings.',
        );
    }

    public function testYiiOmitsGitReferenceForBrandChipReadability(): void
    {
        $framework = VersionResolver::yii();

        self::assertStringNotContainsString(
            ' @',
            $framework,
            'Framework version must omit the SHA suffix to keep the toolbar brand chip stable.',
        );
        self::assertStringNotContainsString(
            '9999999',
            $framework,
            'Synthetic infinity version must not leak into the framework string.',
        );
    }
}
