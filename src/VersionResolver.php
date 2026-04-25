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
 * Replaces Composer's synthetic dev placeholders (`22.0.9999999.9999999-dev`, `dev-master`) with the package alias plus
 * the short Git reference, producing strings like `22.x-dev @a1b2c3d` or `2.0.45`.
 *
 * Usage example:
 * ```php
 * use yii\debug\VersionResolver;
 *
 * $yii = VersionResolver::yii();                              // "22.0.x-dev @4c15f88"
 * $pkg = VersionResolver::forPackage('yiisoft/yii2-debug');   // "22.x-dev @abcdef0" or null
 * $list = VersionResolver::forExtensions(Yii::$app->extensions);
 * ```
 *
 * @author Wilmer Arambula <terabytesoftw@gmail.com>
 * @since 22.0
 */
final class VersionResolver
{
    /**
     * Replaces the `version` field of each entry with the friendly version when resolvable.
     *
     * Accepts the array shape produced by `Yii::$app->extensions` — `[$packageName => ['name' => ..., 'version' => ...]]`.
     * Entries whose package cannot be resolved through Composer are returned unchanged.
     *
     * @param array<string, array<string, mixed>> $extensions
     *
     * @return array<string, array<string, mixed>>
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
     * Returns a friendly version string for a Composer package, or `null` when the package is not installed.
     *
     * For tagged releases the tag is returned verbatim. For dev branches the alias is augmented with the 7-character
     * Git short hash so two builds of the same branch can be told apart.
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
                return $pretty . ' @' . substr($reference, 0, 7);
            }
        }

        return $pretty;
    }

    /**
     * Returns a friendly version for the Yii framework, without the Git reference.
     *
     * The framework version sits in the toolbar brand chip and is read at a glance — the short SHA is noisy
     * there and would shift on every framework rebuild. Falls back to {@see Yii::getVersion()} when Composer
     * runtime metadata is unavailable.
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
