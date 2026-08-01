<?php

declare(strict_types=1);

namespace yii\debug\models\search;

use Yii;
use yii\base\Model;
use yii\helpers\VarDumper;

use function array_filter;
use function array_key_exists;
use function array_values;
use function is_float;
use function is_int;
use function is_numeric;
use function is_scalar;
use function is_string;
use function preg_match;

/**
 * Shared filtering behavior for debug-panel search models.
 */
class Base extends Model
{
    /**
     * @var list<
     *   array{attribute: string, operator: '>'|'<'|'>=', value: float}
     *   |array{attribute: string, operator: 'contains'|'same', value: string}
     * >
     */
    private array $conditions = [];

    /**
     * Registers an exact, partial, or leading numeric comparison condition.
     */
    protected function addCondition(string $attribute, bool $partial = false): void
    {
        $rawValue = $this->getAttributes([$attribute])[$attribute] ?? null;
        $value = is_scalar($rawValue) ? (string) $rawValue : '';

        if ($value === '') {
            return;
        }

        if (preg_match('/^\s*([<>])\s*(-?(?:\d+(?:\.\d+)?|\.\d+))\s*$/D', $value, $matches) === 1) {
            $this->conditions[] = [
                'attribute' => $attribute,
                'operator' => $matches[1],
                'value' => (float) $matches[2],
            ];

            return;
        }

        $this->conditions[] = [
            'attribute' => $attribute,
            'operator' => $partial ? 'contains' : 'same',
            'value' => $value,
        ];
    }

    /**
     * Registers a numeric greater-than-or-equal condition.
     */
    protected function addMinimumCondition(string $attribute, float $value): void
    {
        $this->conditions[] = ['attribute' => $attribute, 'operator' => '>=', 'value' => $value];
    }

    /**
     * Applies all registered conditions.
     *
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<int, array<string, mixed>>
     */
    protected function filter(array $rows): array
    {
        $filtered = array_values(array_filter($rows, $this->matches(...)));
        $this->conditions = [];

        return $filtered;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function matches(array $row): bool
    {
        foreach ($this->conditions as $condition) {
            $attribute = $condition['attribute'];

            if (!array_key_exists($attribute, $row)) {
                return false;
            }

            $candidate = $row[$attribute];
            $operator = $condition['operator'];

            if ($operator === '>' || $operator === '<' || $operator === '>=') {
                $expected = $condition['value'];

                if (
                    !is_float($expected)
                    || (!is_int($candidate) && !is_float($candidate) && !is_string($candidate))
                    || !is_numeric($candidate)
                ) {
                    return false;
                }

                $candidate = (float) $candidate;
                $matched = match ($operator) {
                    '>' => $candidate > $expected,
                    '<' => $candidate < $expected,
                    default => $candidate >= $expected,
                };

                if (!$matched) {
                    return false;
                }

                continue;
            }

            $candidate = is_scalar($candidate) ? (string) $candidate : VarDumper::export($candidate);
            $expected = $condition['value'];

            if (!is_string($expected)) {
                return false;
            }

            if ($operator === 'contains') {
                if (mb_stripos($candidate, $expected, 0, Yii::$app->charset) === false) {
                    return false;
                }

                continue;
            }

            if (
                mb_strtolower($candidate, Yii::$app->charset)
                !== mb_strtolower($expected, Yii::$app->charset)
            ) {
                return false;
            }
        }

        return true;
    }
}
