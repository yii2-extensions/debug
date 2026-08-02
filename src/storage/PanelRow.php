<?php

declare(strict_types=1);

namespace yii\debug\storage;

use JsonSerializable;

/**
 * Marker contract for a typed row held by a panel snapshot.
 *
 * Rows are narrowed once, at capture time, and persisted in that typed form; hydration restores them through
 * {@see Payload} without coercion.
 */
interface PanelRow extends JsonSerializable
{
    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array;
}
