<?php

declare(strict_types=1);

namespace yii\debug\panels\asset;

use yii\debug\storage\Payload;

use function array_map;

/**
 * Typed snapshot of the Vite bridge configuration and its build manifest.
 */
final readonly class ViteManifest
{
    /**
     * @param list<ViteChunk> $chunks
     */
    public function __construct(
        public string $baseUrl,
        public bool $devMode,
        public string|null $devServerUrl,
        public string $manifestPath,
        public array $chunks,
    ) {}

    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)
            ->shape(
                [
                    'baseUrl',
                    'devMode',
                    'devServerUrl',
                    'manifestPath',
                    'chunks',
                ],
            );

        $chunks = [];

        foreach ($payload->list('chunks') as $index => $chunk) {
            $chunks[] = ViteChunk::fromArray($chunk, "{$path}.chunks[{$index}]");
        }

        return new self(
            $payload->string('baseUrl'),
            $payload->bool('devMode'),
            $payload->nullableString('devServerUrl'),
            $payload->string('manifestPath'),
            $chunks,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'baseUrl' => $this->baseUrl,
            'devMode' => $this->devMode,
            'devServerUrl' => $this->devServerUrl,
            'manifestPath' => $this->manifestPath,
            'chunks' => array_map(static fn(ViteChunk $chunk): array => $chunk->jsonSerialize(), $this->chunks),
        ];
    }
}
