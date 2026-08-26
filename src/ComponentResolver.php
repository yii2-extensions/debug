<?php

declare(strict_types=1);

namespace yii\debug;

use Yii;
use yii\base\InvalidConfigException;

use function class_exists;
use function is_array;
use function is_callable;
use function is_object;
use function is_string;

/**
 * Normalizes Yii component configuration values into a resolvable type and its remaining properties.
 */
final class ComponentResolver
{
    /**
     * Splits a configuration value into a resolvable class or container identifier and its remaining properties.
     *
     * The Yii-specific `__class` key takes precedence over `class`, matching {@see Yii::createObject()}.
     *
     * @param array<array-key, mixed>|string $config Class/container ID or configuration array with a type key.
     * @param string|null $defaultClass Type assumed when a configuration array omits both type keys.
     *
     * @return array{string|null, array<array-key, mixed>} Resolvable type (or `null`) and configuration properties
     * without the `class` and `__class` entries.
     */
    public static function classAndProperties(array|string $config, string|null $defaultClass = null): array
    {
        if (is_string($config)) {
            $class = $config;
            $properties = [];
        } else {
            $class = $config['__class'] ?? $config['class'] ?? $defaultClass;
            $properties = $config;

            unset($properties['__class'], $properties['class']);
        }

        if (!is_string($class) || !self::isResolvable($class)) {
            return [null, $properties];
        }

        return [$class, $properties];
    }

    /**
     * Creates the object described by an action-map entry, validating the named class before instantiation.
     *
     * @param mixed $config Action-map entry accepted by {@see Yii::createObject()}.
     *
     * @throws InvalidConfigException When object creation fails for a resolvable configuration.
     *
     * @return object|null Instantiated object, or `null` when the entry is unresolvable or produces no object.
     */
    public static function createMapped(mixed $config): object|null
    {
        if (is_string($config)) {
            [$class] = self::classAndProperties($config);

            if ($class === null) {
                return null;
            }
        } elseif (is_callable($config, true)) {
            // Callables are resolved directly by Yii's container and therefore have no class name to prevalidate.
        } elseif (is_array($config)) {
            [$class] = self::classAndProperties($config);

            if ($class === null) {
                return null;
            }
        } else {
            return null;
        }

        // Instantiate the full mapped configuration after validating named targets, preserving configured properties.
        // TODO: drop the ignore once `yii2-extensions/phpstan` stubs `Yii::createObject()` for the complete action-map
        // value.
        $object = Yii::createObject($config); // @phpstan-ignore argument.type

        return is_object($object) ? $object : null;
    }

    /**
     * Returns whether Yii can resolve the class or registered container identifier.
     */
    private static function isResolvable(string $class): bool
    {
        return class_exists($class) || Yii::$container->has($class);
    }
}
