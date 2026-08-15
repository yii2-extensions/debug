<?php

declare(strict_types=1);

namespace yii\debug\panels\inertia;

use PHPForge\Debug\Storage\{DebugArray, DebugValue, PanelSnapshot, Payload};

/**
 * Canonical Inertia response snapshot.
 */
final readonly class InertiaSnapshot implements PanelSnapshot
{
    public function __construct(
        public string|null $location,
        private DebugValue $page,
        private DebugArray $requestHeaders,
        private DebugArray $sharedKeys,
        public int $statusCode,
    ) {}

    /**
     * @param array<array-key, mixed> $requestHeaders
     * @param array<array-key, mixed> $sharedKeys
     */
    public static function capture(
        string|null $location,
        mixed $page,
        array $requestHeaders,
        array $sharedKeys,
        int $statusCode,
    ): self {
        return new self(
            $location,
            DebugValue::capture($page),
            DebugArray::capture($requestHeaders),
            DebugArray::capture($sharedKeys),
            $statusCode,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            'location' => $this->location,
            'page' => $this->page->toDisplayValue(),
            'requestHeaders' => $this->requestHeaders->values(),
            'sharedKeys' => $this->sharedKeys->values(),
            'statusCode' => $this->statusCode,
        ];
    }

    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)
            ->shape(
                [
                    'location',
                    'page',
                    'requestHeaders',
                    'sharedKeys',
                    'statusCode',
                ],
            );

        return new self(
            $payload->nullableString('location'),
            DebugValue::fromArray($payload->raw('page'), "{$path}.page"),
            DebugArray::fromArray($payload->raw('requestHeaders'), "{$path}.requestHeaders"),
            DebugArray::fromArray($payload->raw('sharedKeys'), "{$path}.sharedKeys"),
            $payload->int('statusCode'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'location' => $this->location,
            'page' => $this->page->jsonSerialize(),
            'requestHeaders' => $this->requestHeaders->jsonSerialize(),
            'sharedKeys' => $this->sharedKeys->jsonSerialize(),
            'statusCode' => $this->statusCode,
        ];
    }
}
