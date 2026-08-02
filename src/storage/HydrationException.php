<?php

declare(strict_types=1);

namespace yii\debug\storage;

use RuntimeException;

/**
 * Reports a strict JSON schema violation with its payload path.
 */
final class HydrationException extends RuntimeException
{
    public static function at(string $path, string $expected): self
    {
        return new self("Invalid debug snapshot value at '{$path}': expected {$expected}.");
    }
}
