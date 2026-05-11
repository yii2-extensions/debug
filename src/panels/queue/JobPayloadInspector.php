<?php

declare(strict_types=1);

namespace yii\debug\panels\queue;

use ReflectionClass;
use ReflectionProperty;
use Throwable;

use function get_class;
use function get_resource_type;
use function is_array;
use function is_object;
use function is_resource;
use function is_scalar;

/**
 * Extracts the public-property tree of a job into a normalised, serialisable structure.
 *
 * The panel captures jobs at push time but the saved data is read back later (after the request finishes). Storing the
 * original job object would either hard-couple the renderer to the live class hierarchy or fail to serialise cleanly,
 * so the inspector eagerly walks the public properties via Reflection and produces a tree of scalars + nested arrays
 * that round-trips through JSON / `serialize` without surprises.
 *
 * Output shape:
 *
 * - Scalars (`string` / `int` / `float` / `bool` / `null`) round-trip as-is.
 * - Arrays recurse, with their original keys preserved.
 * - Objects expand into `['__class' => 'FQCN', '<prop>' => <value>, ...]` so the renderer can render the class
 *   header. Beyond the depth limit, an object collapses to `['__class' => 'FQCN', '__truncated' => true]`.
 * - Resources are stringified as `'(resource: <type>)'`.
 *
 * Usage example:
 * ```php
 * $fields = JobPayloadInspector::extract(new \app\jobs\HelloJob('hello world'));
 * // ['message' => 'hello world']
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class JobPayloadInspector
{
    /**
     * Maximum recursion depth; guards against pathological cycles in nested objects/arrays.
     */
    private const int MAX_DEPTH = 6;

    /**
     * Extracts the public properties of an object as a recursively normalised array. The synthetic `__class` key is
     * NOT included on the top-level extraction (the renderer already knows the job class via `JobRecord::$jobClass`)
     * but IS added when nested objects are expanded so each sub-tree carries its own type label.
     *
     * @return array<string, mixed>
     */
    public static function extract(object $job): array
    {
        return self::extractPublicProperties($job, 0);
    }

    /**
     * @return array<string, mixed>
     */
    private static function extractPublicProperties(object $value, int $depth): array
    {
        $reflection = new ReflectionClass($value);

        $fields = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();

            try {
                $raw = $property->getValue($value);
            } catch (Throwable) {
                $fields[$name] = '(unreadable)';

                continue;
            }

            $fields[$name] = self::normalizeValue($raw, $depth + 1);
        }

        return $fields;
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     */
    private static function normalizeArray(array $value, int $depth): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return ['__truncated' => true];
        }

        $out = [];

        foreach ($value as $key => $entry) {
            $out[$key] = self::normalizeValue($entry, $depth + 1);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeObject(object $value, int $depth): array
    {
        $class = get_class($value);

        if ($depth >= self::MAX_DEPTH) {
            return ['__class' => $class, '__truncated' => true];
        }

        return ['__class' => $class] + self::extractPublicProperties($value, $depth);
    }

    private static function normalizeValue(mixed $value, int $depth): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            return self::normalizeArray($value, $depth);
        }

        if (is_object($value)) {
            return self::normalizeObject($value, $depth);
        }

        if (is_resource($value)) {
            return '(resource: ' . get_resource_type($value) . ')';
        }

        return '(unsupported)';
    }
}
