<?php

declare(strict_types=1);

namespace yii\debug\panels\asset;

/**
 * Typed view-model for a single asset bundle rendered in the Asset Bundles detail view.
 *
 * Carries every value already normalized from the panel's `mixed` payload — the consuming view iterates and reads
 * properties without further type narrowing.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final readonly class AssetBundleView
{
    public function __construct(
        /**
         * Fully qualified class name of the asset bundle.
         */
        public string $name,
        /**
         * Class basename (last segment of the FQCN).
         */
        public string $shortName,
        /**
         * Namespace prefix without the trailing backslash; empty when `$name` has no namespace.
         */
        public string $namespace,
        /**
         * Anchor id derived from the FQCN via `Inflector::camel2id()`.
         */
        public string $id,
        /**
         * Bundle `sourcePath` or empty string when missing or not a string.
         */
        public string $sourcePath,
        /**
         * Bundle `basePath` or empty string when missing or not a string.
         */
        public string $basePath,
        /**
         * Bundle `baseUrl` or empty string when missing or not a string.
         */
        public string $baseUrl,
        /**
         * @var list<string> CSS file labels array entries already unwrapped to their first element.
         */
        public array $css,
        /**
         * @var list<string> JS file labels array entries already unwrapped to their first element.
         */
        public array $js,
        /**
         * @var list<string> Fully qualified class names of dependent bundles.
         */
        public array $depends,
        /**
         * Number of CSS files.
         */
        public int $cssCount,
        /**
         * Number of JS files.
         */
        public int $jsCount,
        /**
         * Number of declared dependencies.
         */
        public int $depsCount,
        /**
         * `true` when the bundle declares at least one CSS or JS file.
         */
        public bool $hasFiles,
        /**
         * `true` when at least one of `sourcePath`, `basePath`, `baseUrl` is non-empty.
         */
        public bool $hasWiring,
        /**
         * `true` when the bundle declares at least one dependency.
         */
        public bool $hasDepends,
        /**
         * Layout hint: `2` when both Files and (Wiring or Depends) sections are visible, `1` otherwise.
         */
        public int $bodyCols,
    ) {}
}
