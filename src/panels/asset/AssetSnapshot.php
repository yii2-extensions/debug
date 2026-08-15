<?php

declare(strict_types=1);

namespace yii\debug\panels\asset;

use PHPForge\Debug\Storage\{PanelSnapshot, Payload};

use function array_map;

/**
 * Canonical Asset panel snapshot holding the registered bundles and the Vite manifest in their typed form.
 */
final readonly class AssetSnapshot implements PanelSnapshot
{
    /**
     * @param list<AssetBundleRow> $bundles
     */
    public function __construct(private array $bundles, private ViteManifest|null $vite) {}

    /**
     * @return list<AssetBundleRow> Registered bundles in registration order.
     */
    public function bundles(): array
    {
        return $this->bundles;
    }

    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)->shape(['bundles', 'vite']);

        $bundles = [];

        foreach ($payload->list('bundles') as $index => $bundle) {
            $bundles[] = AssetBundleRow::fromArray($bundle, "{$path}.bundles[{$index}]");
        }

        $vite = $payload->raw('vite');

        return new self($bundles, $vite === null ? null : ViteManifest::fromArray($vite, "{$path}.vite"));
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'bundles' => array_map(static fn(AssetBundleRow $row): array => $row->jsonSerialize(), $this->bundles),
            'vite' => $this->vite?->jsonSerialize(),
        ];
    }

    /**
     * Returns the Vite manifest snapshot, or `null` when no Vite bridge is registered.
     */
    public function vite(): ViteManifest|null
    {
        return $this->vite;
    }
}
