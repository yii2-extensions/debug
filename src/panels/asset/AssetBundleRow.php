<?php

declare(strict_types=1);

namespace yii\debug\panels\asset;

use yii\debug\helpers\Coerce;
use yii\debug\storage\{PanelRow, Payload};

use function is_array;
use function is_string;
use function reset;

/**
 * Typed asset-bundle row narrowed once from the live {@see \yii\web\AssetBundle} and persisted in that form.
 */
final readonly class AssetBundleRow implements PanelRow
{
    public function __construct(
        /**
         * Bundle class name as registered on the asset manager.
         */
        public string $name,
        /**
         * Source path declared by the bundle, or `''` when it publishes nothing.
         */
        public string $sourcePath,
        /**
         * Published base path, or `''` when the bundle was never published.
         */
        public string $basePath,
        /**
         * Published base URL, or `''` when the bundle was never published.
         */
        public string $baseUrl,
        /**
         * @var list<string> CSS files declared by the bundle.
         */
        public array $css,
        /**
         * @var list<string> JavaScript files declared by the bundle.
         */
        public array $js,
        /**
         * @var list<string> Bundle class names this bundle depends on.
         */
        public array $depends,
    ) {}

    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)->shape([
            'name',
            'sourcePath',
            'basePath',
            'baseUrl',
            'css',
            'js',
            'depends',
        ]);

        return new self(
            name: $payload->string('name'),
            sourcePath: $payload->string('sourcePath'),
            basePath: $payload->string('basePath'),
            baseUrl: $payload->string('baseUrl'),
            css: Coerce::stringList($payload->list('css')),
            js: Coerce::stringList($payload->list('js')),
            depends: Coerce::stringList($payload->list('depends')),
        );
    }

    /**
     * Narrows one live asset bundle into a typed row.
     *
     * @param array<array-key, mixed> $bundle Serialized bundle properties.
     */
    public static function fromBundle(string $name, array $bundle): self
    {
        return new self(
            name: $name,
            sourcePath: Coerce::string($bundle['sourcePath'] ?? null),
            basePath: Coerce::string($bundle['basePath'] ?? null),
            baseUrl: Coerce::string($bundle['baseUrl'] ?? null),
            css: self::fileList($bundle, 'css'),
            js: self::fileList($bundle, 'js'),
            depends: Coerce::stringList($bundle['depends'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'sourcePath' => $this->sourcePath,
            'basePath' => $this->basePath,
            'baseUrl' => $this->baseUrl,
            'css' => $this->css,
            'js' => $this->js,
            'depends' => $this->depends,
        ];
    }

    /**
     * Flattens a bundle file list, unwrapping the `[file, options]` shape Yii allows.
     *
     * @param array<array-key, mixed> $bundle Serialized bundle properties.
     *
     * @return list<string> File names in declaration order.
     */
    private static function fileList(array $bundle, string $key): array
    {
        $raw = $bundle[$key] ?? null;

        if (!is_array($raw)) {
            return [];
        }

        $files = [];

        foreach ($raw as $item) {
            if (is_array($item)) {
                $item = reset($item);
            }

            if (is_string($item)) {
                $files[] = $item;
            }
        }

        return $files;
    }
}
