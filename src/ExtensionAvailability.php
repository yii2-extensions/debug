<?php

declare(strict_types=1);

namespace yii\debug;

use yii\debug\panels\{JsonPanel, ProviderPanel};

/**
 * Detects optional debugger integrations through the runtime classes they provide, and classifies the panels that
 * belong to an extension rather than to the built-in Yii diagnostics.
 *
 * {@see ProviderCatalog} declares the integrations the debugger wires on its own; the built-in panels gated on an
 * optional package are listed here. Availability decides whether such a panel is registered, never how it is
 * grouped: Mail and Queue are built-in diagnostics, and only provider-owned or payload-only panels are extensions.
 */
final class ExtensionAvailability
{
    /**
     * @var array<string, non-empty-list<non-empty-string>> Runtime provider class names indexed by debugger panel ID.
     */
    private const array PROVIDERS = [
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
        $catalog = ProviderCatalog::packaged();

        if ($catalog->has($id)) {
            return $catalog->isInstalled($id);
        }

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
     * @return bool `true` when the panel is provider-backed, payload-only, or declared by {@see ProviderCatalog};
     * `false` for every built-in Yii diagnostic, including the ones gated on an optional package.
     */
    public static function isExtensionPanel(string $id, Panel $panel): bool
    {
        return $panel instanceof ProviderPanel || $panel instanceof JsonPanel || ProviderCatalog::packaged()->has($id);
    }
}
