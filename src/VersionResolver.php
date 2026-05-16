<?php

declare(strict_types=1);

namespace yii\debug;

use Composer\InstalledVersions;
use Yii;

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
        if (!InstalledVersions::isInstalled($package)) {
            return null;
        }

        $pretty = InstalledVersions::getPrettyVersion($package);

        // Defensive guard: Composer `InstalledVersions::getPrettyVersion()` can only return `null` when the package
        // metadata is malformed in `installed.json`. Unreachable under normal test conditions.
        // @codeCoverageIgnoreStart
        if ($pretty === null) {
            return null;
        }
        // @codeCoverageIgnoreEnd

        if (str_contains($pretty, 'dev')) {
            $reference = InstalledVersions::getReference($package);

            if ($reference !== null) {
                return "{$pretty} @" . substr($reference, 0, 7);
            }
        }

        return $pretty;
    }

    /**
     * Returns the Yii framework version for the toolbar brand chip.
     */
    public static function yii(): string
    {
        return Yii::getVersion();
    }
}
