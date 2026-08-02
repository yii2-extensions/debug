<?php

declare(strict_types=1);

namespace yii\debug\storage;

/**
 * Provides the capture/hydrate/serialize cycle for panels whose payload stays genuinely dynamic (application
 * configuration, dumped values, and user-configured request globals).
 *
 * The using class declares the JSON key its payload is persisted under as a `KEY` constant, and exposes the payload
 * through a domain-named accessor delegating to {@see values()}.
 */
trait ArrayPayloadSnapshot
{
    final public function __construct(private readonly DebugArray $payload) {}

    /**
     * @param array<array-key, mixed> $values Raw payload captured for the request.
     */
    public static function capture(array $values): self
    {
        return new self(DebugArray::capture($values));
    }

    public static function fromArray(mixed $data, string $path): self
    {
        return new self(Payload::object($data, $path)->shape([self::KEY])->debugArray(self::KEY));
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [self::KEY => $this->payload->jsonSerialize()];
    }

    /**
     * @return array<array-key, mixed> Payload restored to plain PHP values.
     */
    protected function values(): array
    {
        return $this->payload->values();
    }
}
