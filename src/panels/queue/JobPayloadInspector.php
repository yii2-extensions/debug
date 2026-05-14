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
 * Extracts the public-property tree of a job into a normalized, serializable structure.
 *
 * The panel captures jobs at push time but the saved data is read back later (after the request finishes). Storing the
 * original job object would either hard-couple the renderer to the live class hierarchy or fail to serialize cleanly,
 * so the inspector eagerly walks the public properties via Reflection and produces a tree of scalars + nested arrays
 * that round-trips through JSON / `serialize` without surprises.
 *
 * Output shape:
 *
 * - Arrays recurse, with their original keys preserved.
 * - Objects expand into `['__class' => 'FQCN', '<prop>' => <value>, ...]`, so the renderer can render the class header.
 *   Beyond the depth limit, an object collapses to `['__class' => 'FQCN', '__truncated' => true]`.
 * - Resources are stringified as `'(resource: <type>)'`.
 * - Scalars (`string` / `int` / `float` / `bool` / `null`) round-trip as-is.
 */
final class JobPayloadInspector
{
    /**
     * Maximum recursion depth, guarding against pathological cycles in nested objects/arrays.
     */
    private const int MAX_DEPTH = 6;

    /**
     * Per-class cache of public-property reflections.
     *
     * The public-property list is class-stable, so reflecting the same job class on every queue event would do
     * redundant work; this map amortizes the cost to one reflection per class for the lifetime of the worker.
     *
     * @var array<class-string, list<ReflectionProperty>>
     */
    private static array $publicPropertiesByClass = [];

    /**
     * Extracts the public properties of an object as a recursively normalized array.
     *
     * The synthetic `__class` key is NOT included on the top-level extraction (the renderer already knows the job class
     * via {@see JobRecord::$jobClass}) but IS added when nested objects are expanded, so each sub-tree carries its own
     * type label.
     *
     * @param object $job Job instance to inspect.
     *
     * @return array<string, mixed> Top-level property map keyed by property name.
     */
    public static function extract(object $job): array
    {
        return self::extractPublicProperties($job, 0);
    }

    /**
     * Reads each public property of `$value` and normalizes the underlying value, returning a key/value map.
     *
     * @return array<string, mixed> Property map keyed by property name; unreadable properties surface as
     * `'(unreadable)'`.
     */
    private static function extractPublicProperties(object $value, int $depth): array
    {
        $fields = [];

        foreach (self::publicPropertiesOf($value) as $property) {
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
     * Normalizes an array recursively, collapsing to `['__truncated' => true]` once the depth limit is reached.
     *
     * @param array<array-key, mixed> $value Array to normalize.
     *
     * @return array<array-key, mixed> Normalized array preserving the original keys.
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
     * Normalizes an object by promoting its FQCN into `__class` and recursing into its public properties.
     *
     * Collapses to `['__class' => FQCN, '__truncated' => true]` once the depth limit is reached.
     *
     * @return array<string, mixed> Object map carrying `__class` plus any expanded properties.
     */
    private static function normalizeObject(object $value, int $depth): array
    {
        $class = get_class($value);

        if ($depth >= self::MAX_DEPTH) {
            return ['__class' => $class, '__truncated' => true];
        }

        return ['__class' => $class] + self::extractPublicProperties($value, $depth);
    }

    /**
     * Normalizes a single value: scalars/`null` pass through, arrays/objects recurse, resources stringify, anything
     * else collapses to `'(unsupported)'`.
     */
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

    /**
     * Returns the public-property reflections of `$value`'s class, cached per class for the lifetime of the worker.
     *
     * @return list<ReflectionProperty> Reflection objects for the public properties, in declaration order.
     */
    private static function publicPropertiesOf(object $value): array
    {
        $class = get_class($value);

        if (!isset(self::$publicPropertiesByClass[$class])) {
            $reflection = new ReflectionClass($value);
            self::$publicPropertiesByClass[$class] = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        }

        return self::$publicPropertiesByClass[$class];
    }
}
