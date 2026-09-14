<?php

declare(strict_types=1);

namespace yii\debug;

/**
 * Detects optional debugger integrations through the runtime classes they provide.
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
