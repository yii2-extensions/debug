<?php

declare(strict_types=1);

namespace yii\debug\panels\request;

use yii\debug\storage\{DebugArray, HydrationException, PanelSnapshot, Payload};

use function is_int;

/**
 * Canonical Request panel snapshot.
 *
 * The payload remains dynamic because configured global variables are user-defined, while the response status is
 * validated independently as a stable panel invariant.
 */
final readonly class RequestSnapshot implements PanelSnapshot
{
    private function __construct(
        private DebugArray $data,
        public int $statusCode,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function capture(array $data): self
    {
        $statusCode = $data['statusCode'] ?? null;

        if (!is_int($statusCode)) {
            throw HydrationException::at('$.panels.request.statusCode', 'an integer');
        }

        return new self(DebugArray::capture($data), $statusCode);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function data(): array
    {
        return $this->data->values();
    }

    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)->shape(['data', 'statusCode']);
        $snapshotData = DebugArray::fromArray($payload->raw('data'), "{$path}.data");
        $statusCode = $payload->int('statusCode');
        $values = $snapshotData->values();

        if (($values['statusCode'] ?? null) !== $statusCode) {
            throw HydrationException::at("{$path}.statusCode", 'the status code stored in data');
        }

        return new self($snapshotData, $statusCode);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'data' => $this->data->jsonSerialize(),
            'statusCode' => $this->statusCode,
        ];
    }
}
