<?php

declare(strict_types=1);

namespace yii\inertia;

use JsonSerializable;

/**
 * Stand-in for the `yii2-extensions/inertia` page payload, loaded only when the real package is absent.
 */
final readonly class Page implements JsonSerializable
{
    /**
     * @param string $component Component name rendered for the visit.
     * @param array<string, mixed> $props Resolved page props.
     * @param string $url Request URL echoed back to the client.
     * @param string $version Asset version fingerprint.
     */
    public function __construct(
        private string $component,
        private array $props,
        private string $url,
        private string $version,
    ) {}

    /**
     * @return array<string, mixed> Serialized page payload mirroring the real adapter's shape.
     */
    public function jsonSerialize(): array
    {
        return [
            'component' => $this->component,
            'props' => $this->props,
            'url' => $this->url,
            'version' => $this->version,
        ];
    }
}
