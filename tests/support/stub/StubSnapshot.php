<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use yii\debug\storage\{DebugArray, PanelSnapshot};

/**
 * JSON-safe snapshot used by custom test panels.
 */
final readonly class StubSnapshot implements PanelSnapshot
{
    public function __construct(private DebugArray $data) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function capture(array $data): self
    {
        return new self(DebugArray::capture($data));
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return ['data' => $this->data->jsonSerialize()];
    }
}
