<?php

declare(strict_types=1);

namespace yii\debug;

use Composer\InstalledVersions;
use Yii;

use function class_exists;
use function is_string;
use function str_contains;
use function substr;

/**
 * Resolves human-readable Composer package versions for the debug UI.
 *
 * Replaces Composer's synthetic dev placeholders (`22.0.9999999.9999999-dev`, `dev-master`) with the package alias
 * plus the short Git reference, producing strings such as `22.x-dev @a1b2c3d` or `2.0.45`.
 */
final class VersionResolver
{
    /**
     * Rewrites the `version` field of each entry with the friendly version, when resolvable.
     *
     * Accepts the array shape produced by `Yii::$app->extensions`; `[$packageName => ['name' => ..., 'version' =>
     * ...]]`. Entries whose key is not a string (malformed registrations) or whose package cannot be resolved through
     * Composer are returned unchanged.
     *
     * @param array<int|string, array{
     *   name?: string,
     *   version?: string,
     *   bootstrap?: string|array<string, mixed>,
     *   alias?: array<string, string>,
     * }> $extensions Extension map keyed by package name.
     *
     * @return array<int|string, array{
     *   name?: string,
     *   version?: string,
     *   bootstrap?: string|array<string, mixed>,
     *   alias?: array<string, string>,
     * }> Extension map with `version` fields rewritten in place.
     */
    public static function forExtensions(array $extensions): array
    {
        foreach ($extensions as $name => &$extension) {
            if (!is_string($name)) {
                continue;
            }

            $friendly = self::forPackage($name);

            if ($friendly !== null) {
                $extension['version'] = $friendly;
            }
        }

        unset($extension);

        return $extensions;
    }

    /**
     * Returns a friendly version string for a Composer package.
     *
     * Tagged releases are returned verbatim. Dev branches are augmented with the 7-character Git short hash so two
     * builds of the same branch can be told apart.
     *
     * @param string $package Composer package name (`vendor/package`).
     *
     * @return string|null Friendly version, or `null` when the package is not installed or has no pretty version.
     */
    public static function forPackage(string $package): string|null
    {
        if (!class_exists(InstalledVersions::class) || !InstalledVersions::isInstalled($package)) {
            return null;
        }

        $pretty = InstalledVersions::getPrettyVersion($package);

        if ($pretty === null) {
            return null;
        }

        if (str_contains($pretty, 'dev')) {
            $reference = InstalledVersions::getReference($package);

            if ($reference !== null) {
                return "{$pretty} @" . substr($reference, 0, 7);
            }
        }

        return $pretty;
    }

    /**
     * Returns a friendly version for the Yii framework, without the Git reference.
     *
     * The framework version sits in the toolbar brand chip and is read at a glance; the short SHA is noisy there and
     * would shift on every framework rebuild. Falls back to {@see Yii::getVersion()} when Composer runtime metadata
     * is unavailable.
     *
     * @return string Framework version string ready for display.
     */
    public static function yii(): string
    {
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('yiisoft/yii2')) {
            $pretty = InstalledVersions::getPrettyVersion('yiisoft/yii2');

            if ($pretty !== null) {
                return $pretty;
            }
        }

        return Yii::getVersion();
    }
}
