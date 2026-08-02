<?php

declare(strict_types=1);

namespace yii\debug\storage;

use JsonSerializable;

/**
 * Marker contract for a typed debug-panel snapshot.
 */
interface PanelSnapshot extends JsonSerializable
{
    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array;
}
