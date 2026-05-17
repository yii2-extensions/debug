<?php

declare(strict_types=1);

namespace yii\debug\panels\asset;

/**
 * Typed view-model for a single asset bundle rendered in the Asset Bundles detail view.
 *
 * Carries every value already normalized from the panel's mixed payload, so the consuming view iterates and reads
 * properties without further type narrowing.
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
         * Namespace prefix without the trailing backslash, or `''` when {@see $name} has no namespace.
         */
        public string $namespace,
        /**
         * Anchor id derived from the FQCN via {@see \yii\helpers\Inflector::camel2id()}.
         */
        public string $id,
        /**
         * Bundle `sourcePath` value, or `''` when missing or non-string.
         */
        public string $sourcePath,
        /**
         * Bundle `basePath` value, or `''` when missing or non-string.
         */
        public string $basePath,
        /**
         * Bundle `baseUrl` value, or `''` when missing or non-string.
         */
        public string $baseUrl,
        /**
         * @var list<string> CSS file labels, with single-element wrapper arrays already unwrapped to their first
         * element.
         */
        public array $css,
        /**
         * @var list<string> JS file labels, with single-element wrapper arrays already unwrapped to their first
         * element.
         */
        public array $js,
        /**
         * @var list<string> Fully qualified class names of dependent bundles.
         */
        public array $depends,
        /**
         * Number of CSS files in {@see $css}.
         */
        public int $cssCount,
        /**
         * Number of JS files in {@see $js}.
         */
        public int $jsCount,
        /**
         * Number of declared dependencies in {@see $depends}.
         */
        public int $depsCount,
        /**
         * `true` when the bundle declares at least one CSS or JS file.
         */
        public bool $hasFiles,
        /**
         * `true` when at least one of {@see $sourcePath}, {@see $basePath}, {@see $baseUrl} is non-empty.
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
