<?php

declare(strict_types=1);

namespace yii\debug;

use yii\debug\panels\{JsonPanel, ProviderPanel};

/**
 * Detects optional debugger integrations through the runtime classes they provide, and classifies the panels that
 * belong to an extension rather than to the built-in Yii diagnostics.
 */
final class ExtensionAvailability
{
    /**
     * @var array<string, non-empty-list<non-empty-string>> Runtime provider class names indexed by debugger panel ID.
     */
    private const array PROVIDERS = [
        'mail' => ['yii\symfonymailer\Mailer'],
        'queue' => ['yii\queue\Queue'],
    ];

    /**
     * Returns whether the integration behind `$id` is installed.
     *
     * @param string $id Panel id backed by an optional integration.
     *
     * @return bool `true` when the integration is installed, or when the id needs none.
     */
    public static function isAvailable(string $id): bool
    {
        $providers = self::PROVIDERS[$id] ?? null;

        if ($providers === null) {
            return true;
        }

        foreach ($providers as $provider) {
            if (class_exists($provider)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns whether a registered panel belongs to an extension rather than to the built-in Yii diagnostics.
     *
     * Shared by the toolbar and the sidebar so both renderers classify, group, and sort the same entries.
     *
     * @param string $id Panel id under which the panel is registered.
     * @param Panel $panel Registered panel instance.
     *
     * @return bool `true` when the panel is provider-backed, payload-only, or bound to an optional package; `false`
     * otherwise.
     */
    public static function isExtensionPanel(string $id, Panel $panel): bool
    {
        return $panel instanceof ProviderPanel || $panel instanceof JsonPanel || self::isOptional($id);
    }

    /**
     * Returns whether `$id` belongs to an optional integration rather than the built-in Yii diagnostics.
     *
     * @param string $id Panel id to classify.
     *
     * @return bool `true` when the panel depends on an optional package; `false` otherwise.
     */
    public static function isOptional(string $id): bool
    {
        return isset(self::PROVIDERS[$id]);
    }
}
