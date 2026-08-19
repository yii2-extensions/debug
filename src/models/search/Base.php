<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use PHPForge\Debug\Data\FilterEngine;
use yii\base\Model;

/**
 * Bridges Yii2 search-model attributes to the shared framework-neutral filter engine.
 */
class Base extends Model
{
    private FilterEngine|null $filterEngine = null;

    /**
     * Registers an exact, partial, or leading numeric comparison condition.
     */
    protected function addCondition(string $attribute, bool $partial = false): void
    {
        $rawValue = $this->getAttributes([$attribute])[$attribute] ?? null;

        $this->engine()->addCondition($attribute, $rawValue, $partial);
    }

    /**
     * Registers a numeric greater-than-or-equal condition.
     */
    protected function addMinimumCondition(string $attribute, float $value): void
    {
        $this->engine()->addMinimumCondition($attribute, $value);
    }

    /**
     * Applies all registered conditions.
     *
     * @template TRow of array<string, mixed>|object
     *
     * @param array<int, TRow> $rows Rows to filter; typed row objects or string-keyed arrays.
     *
     * @return list<TRow> Rows matching every registered condition, reindexed.
     */
    protected function filter(array $rows): array
    {
        return $this->engine()->filter($rows);
    }

    private function engine(): FilterEngine
    {
        return $this->filterEngine ??= new FilterEngine();
    }
}
