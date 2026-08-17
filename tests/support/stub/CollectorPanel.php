<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub;

use Override;
use PHPForge\Debug\Storage\{DebugArray, PanelSnapshot};
use RuntimeException;
use yii\debug\Panel;
use yii\helpers\Html;

/**
 * Presents custom collector data and provides a legacy capture path for Yii2 integration tests.
 */
final class CollectorPanel extends Panel
{
    public int $captureCount = 0;
    public bool $collectorOnly = true;
    public mixed $legacyValue = 'legacy';

    /**
     * @var array<array-key, mixed> Hydrated custom data.
     */
    private array $data = [];

    #[Override]
    public function capture(): PanelSnapshot
    {
        ++$this->captureCount;

        if ($this->collectorOnly) {
            throw new RuntimeException('Matching panel capture must not run.');
        }

        return StubSnapshot::capture(['value' => $this->legacyValue]);
    }

    #[Override]
    public function getDetail(): string
    {
        $value = $this->data['value'] ?? '';

        return Html::encode(is_scalar($value) ? (string) $value : '');
    }

    #[Override]
    public function getName(): string
    {
        return 'Example';
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[Override]
    public function hydrate(array $payload): void
    {
        $this->data = DebugArray::fromArray($payload['data'] ?? null, "$.panels.{$this->id}.data")->values();
    }
}
