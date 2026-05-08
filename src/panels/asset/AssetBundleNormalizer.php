<?php

declare(strict_types=1);

namespace yii\debug\panels\asset;

use yii\helpers\Inflector;

use function count;
use function is_array;
use function is_string;
use function reset;
use function strrpos;
use function substr;

/**
 * Normalizes the `mixed` payload of {@see \yii\debug\panels\AssetPanel} into a typed {@see AssetSummary} tree.
 *
 * Centralizes every `is_array` / `is_string` narrowing previously inlined in the detail view, so the rendering layer
 * can iterate typed DTOs without further runtime type checks.
 *
 * Usage example:
 * ```php
 * $summary = (new \yii\debug\panels\asset\AssetBundleNormalizer())->normalize($panel->data);
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class AssetBundleNormalizer
{
    /**
     * Converts a raw asset-panel payload into a typed summary.
     *
     * Accepts whatever shape `Panel::$data` happens to carry — non-array input or malformed entries are silently
     * dropped instead of triggering a render-time error.
     *
     * @param mixed $data Raw value of `\yii\debug\panels\AssetPanel::$data`.
     *
     * @return AssetSummary Typed summary safe to render directly.
     */
    public function normalize(mixed $data): AssetSummary
    {
        if (!is_array($data) || $data === []) {
            return new AssetSummary([], 0, 0, 0, 0);
        }

        $bundles = [];
        $totalCss = 0;
        $totalJs = 0;
        $totalDeps = 0;

        foreach ($data as $name => $bundle) {
            if (!is_string($name) || !is_array($bundle)) {
                continue;
            }

            $css = $this->extractFileList($bundle, 'css');
            $js = $this->extractFileList($bundle, 'js');
            $depends = $this->extractStringList($bundle, 'depends');
            $sourcePath = $this->extractString($bundle, 'sourcePath');
            $basePath = $this->extractString($bundle, 'basePath');
            $baseUrl = $this->extractString($bundle, 'baseUrl');

            $cssCount = count($css);
            $jsCount = count($js);
            $depsCount = count($depends);

            $hasFiles = $cssCount + $jsCount > 0;
            $hasWiring = $sourcePath !== '' || $basePath !== '' || $baseUrl !== '';
            $hasDepends = $depsCount > 0;

            $bundles[] = new AssetBundleView(
                name: $name,
                shortName: $this->shortName($name),
                namespace: $this->namespacePart($name),
                id: Inflector::camel2id($name),
                sourcePath: $sourcePath,
                basePath: $basePath,
                baseUrl: $baseUrl,
                css: $css,
                js: $js,
                depends: $depends,
                cssCount: $cssCount,
                jsCount: $jsCount,
                depsCount: $depsCount,
                hasFiles: $hasFiles,
                hasWiring: $hasWiring,
                hasDepends: $hasDepends,
                bodyCols: ($hasFiles && ($hasWiring || $hasDepends)) ? 2 : 1,
            );

            $totalCss += $cssCount;
            $totalJs += $jsCount;
            $totalDeps += $depsCount;
        }

        return new AssetSummary(
            bundles: $bundles,
            totalBundles: count($bundles),
            totalCss: $totalCss,
            totalJs: $totalJs,
            totalDeps: $totalDeps,
        );
    }

    /**
     * Extracts a `list<string>` of file labels for the given bundle key (`css` or `js`).
     *
     * Each entry that is itself an array is unwrapped to its first element — the legacy formatter wraps file paths in
     * `Html::a(...)` markup as a single-element array.
     *
     * @param array<array-key, mixed> $bundle Raw bundle payload.
     * @param string $key Either `css` or `js`.
     *
     * @return list<string>
     */
    private function extractFileList(array $bundle, string $key): array
    {
        $raw = $bundle[$key] ?? null;

        if (!is_array($raw)) {
            return [];
        }

        $out = [];

        foreach ($raw as $item) {
            if (is_array($item)) {
                $first = reset($item);
                $item = $first;
            }

            if (is_string($item)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * Extracts a string value for the given bundle key, falling back to `''` when missing or non-string.
     *
     * @param array<array-key, mixed> $bundle Raw bundle payload.
     * @param string $key One of `sourcePath`, `basePath`, `baseUrl`.
     */
    private function extractString(array $bundle, string $key): string
    {
        $raw = $bundle[$key] ?? null;

        return is_string($raw) ? $raw : '';
    }

    /**
     * Extracts a `list<string>` of dependency FQCNs for the given bundle key.
     *
     * @param array<array-key, mixed> $bundle Raw bundle payload.
     * @param string $key Always `depends` in practice.
     *
     * @return list<string>
     */
    private function extractStringList(array $bundle, string $key): array
    {
        $raw = $bundle[$key] ?? null;

        if (!is_array($raw)) {
            return [];
        }

        $out = [];

        foreach ($raw as $item) {
            if (is_string($item)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * Returns the namespace prefix of a fully qualified class name without the trailing backslash, or `''` when there
     * is no namespace separator.
     */
    private function namespacePart(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? '' : substr($fqcn, 0, $pos);
    }

    /**
     * Returns the last segment of a fully qualified class name, or the input unchanged when there is no namespace
     * separator.
     */
    private function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
