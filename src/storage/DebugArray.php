<?php

declare(strict_types=1);

namespace yii\debug\storage;

use JsonSerializable;

/**
 * JSON-safe representation of a PHP array whose nested values may be arbitrary debug data.
 */
final readonly class DebugArray implements JsonSerializable
{
    private function __construct(private DebugValue $value) {}

    /**
     * @param array<array-key, mixed> $value
     */
    public static function capture(array $value): self
    {
        return new self(DebugValue::capture($value));
    }

    public static function fromArray(mixed $value, string $path): self
    {
        $debugValue = DebugValue::fromArray($value, $path);

        if ($debugValue->type !== 'array') {
            throw HydrationException::at($path, 'a tagged array');
        }

        return new self($debugValue);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->value->jsonSerialize();
    }

    /**
     * @return array<array-key, mixed>
     */
    public function values(): array
    {
        return $this->value->toDisplayEntries();
    }
}
