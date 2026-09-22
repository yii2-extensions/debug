<?php

declare(strict_types=1);

namespace yii\debug\service;

use yii\debug\ExtensionAvailability;

use function array_diff_key;

/**
 * Merges the built-in collector and panel definitions with the ones the application declares.
 */
final class CoreDefinitions
{
    /**
     * Drops the built-ins whose optional integration is unavailable, then appends the configured entries.
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
            if (ExtensionAvailability::isAvailable($id) === false) {
                unset($core[$id]);
            }
        }

        return [...array_diff_key($core, $configured), ...$configured];
    }
}
