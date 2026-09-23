<?php

declare(strict_types=1);

namespace yii\debug\service;

use function array_diff_key;

/**
 * Merges the built-in collector and panel definitions with the ones the application declares.
 */
final class CoreDefinitions
{
    /**
     * @var array<string, non-empty-list<non-empty-string>> Runtime classes, any of which enables the built-in, indexed
     * by built-in ID.
     */
    private const array OPTIONAL = [
        'queue' => ['yii\queue\Queue'],
    ];

    /**
     * Returns whether the optional package a built-in depends on is installed.
     *
     * @param string $id Stable ID of a built-in collector or panel.
     *
     * @return bool `true` when the package is installed, or when the ID depends on none; `false` otherwise.
     */
    public static function isAvailable(string $id): bool
    {
        foreach (self::OPTIONAL[$id] ?? [] as $class) {
            if (class_exists($class)) {
                return true;
            }
        }

        return isset(self::OPTIONAL[$id]) === false;
    }

    /**
     * Drops the built-ins whose optional package is unavailable, then appends the configured entries.
     *
     * A built-in the configuration redeclares under the same ID moves to the configured position, so the application
     * decides both the implementation and the display order of that entry.
     *
     * @template TDefinition
     *
     * @param array<string, TDefinition> $core Built-in definitions indexed by stable ID.
     * @param array<array-key, TDefinition> $configured Definitions declared by the application.
     *
     * @return array<array-key, TDefinition> Available built-ins not overridden by configuration, then the configured
     * entries.
     */
    public static function merge(array $core, array $configured): array
    {
        foreach ($core as $id => $_definition) {
            if (self::isAvailable($id) === false) {
                unset($core[$id]);
            }
        }

        return [...array_diff_key($core, $configured), ...$configured];
    }
}
