<?php

declare(strict_types=1);

namespace yii\debug\storage;

use function array_diff;
use function array_is_list;
use function array_key_exists;
use function array_keys;
use function array_values;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * Strict reader used by DTO factories at the decoded-JSON boundary.
 *
 * It validates types and shapes without accepting numeric strings, casting values, or supplying implicit defaults.
 */
final readonly class Payload
{
    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        private array $data,
        private string $path,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    public function bool(string $key): bool
    {
        $value = $this->value($key);

        if (!is_bool($value)) {
            throw HydrationException::at($this->keyPath($key), 'a boolean');
        }

        return $value;
    }

    /**
     * Reads a tagged array value, keeping the path of the enclosing payload for error reporting.
     */
    public function debugArray(string $key): DebugArray
    {
        return DebugArray::fromArray($this->value($key), $this->keyPath($key));
    }

    public function int(string $key): int
    {
        $value = $this->value($key);

        if (!is_int($value)) {
            throw HydrationException::at($this->keyPath($key), 'an integer');
        }

        return $value;
    }

    /**
     * @return list<mixed>
     */
    public function list(string $key): array
    {
        $value = $this->value($key);

        if (!is_array($value) || !array_is_list($value)) {
            throw HydrationException::at($this->keyPath($key), 'a list');
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function map(string $key): array
    {
        return self::object($this->value($key), $this->keyPath($key))->data;
    }

    public function nullableInt(string $key): int|null
    {
        $value = $this->value($key);

        if ($value === null) {
            return null;
        }

        if (!is_int($value)) {
            throw HydrationException::at($this->keyPath($key), 'an integer or null');
        }

        return $value;
    }

    public function nullableNumber(string $key): float|null
    {
        $value = $this->value($key);

        if ($value === null) {
            return null;
        }

        if (!is_int($value) && !is_float($value)) {
            throw HydrationException::at($this->keyPath($key), 'a number or null');
        }

        return (float) $value;
    }

    public function nullableString(string $key): string|null
    {
        $value = $this->value($key);

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw HydrationException::at($this->keyPath($key), 'a string or null');
        }

        return $value;
    }

    public function number(string $key): float
    {
        $value = $this->value($key);

        if (!is_int($value) && !is_float($value)) {
            throw HydrationException::at($this->keyPath($key), 'a number');
        }

        return (float) $value;
    }

    public static function object(mixed $value, string $path = '$'): self
    {
        if (!is_array($value) || (array_is_list($value) && $value !== [])) {
            throw HydrationException::at($path, 'an object');
        }

        foreach (array_keys($value) as $key) {
            if (!is_string($key)) {
                throw HydrationException::at($path, 'an object with string keys');
            }
        }

        /** @var array<string, mixed> $value */
        return new self($value, $path);
    }

    public function raw(string $key): mixed
    {
        return $this->value($key);
    }

    /**
     * Reads a list of JSON objects, validating each element's shape but leaving its values untouched.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(string $key): array
    {
        $path = $this->keyPath($key);
        $rows = [];

        foreach ($this->list($key) as $index => $row) {
            $rows[] = self::object($row, "{$path}[{$index}]")->data;
        }

        return $rows;
    }

    /**
     * @param list<string> $required
     * @param list<string> $optional
     */
    public function shape(array $required, array $optional = []): self
    {
        foreach ($required as $key) {
            if (!array_key_exists($key, $this->data)) {
                throw HydrationException::at("{$this->path}.{$key}", 'a required field');
            }
        }

        $unknown = array_diff(array_keys($this->data), [...$required, ...$optional]);

        if ($unknown !== []) {
            $key = array_values($unknown)[0];

            throw HydrationException::at("{$this->path}.{$key}", 'a declared field');
        }

        return $this;
    }

    public function string(string $key): string
    {
        $value = $this->value($key);

        if (!is_string($value)) {
            throw HydrationException::at($this->keyPath($key), 'a string');
        }

        return $value;
    }

    private function keyPath(string $key): string
    {
        return "{$this->path}.{$key}";
    }

    private function value(string $key): mixed
    {
        if (!array_key_exists($key, $this->data)) {
            throw HydrationException::at($this->keyPath($key), 'a required field');
        }

        return $this->data[$key];
    }
}
