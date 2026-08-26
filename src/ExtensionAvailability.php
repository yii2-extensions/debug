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
        'inertia' => ['yii\inertia\Manager'],
        'mail' => ['yii\symfonymailer\Mailer'],
        'queue' => ['yii\queue\Queue'],
        'vite' => ['PHPForge\Vite\Vite', 'yii\inertia\Vite'],
    ];

    /**
     * Returns whether the integration behind `$id` is installed.
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
     */
    public static function isOptional(string $id): bool
    {
        return isset(self::PROVIDERS[$id]);
    }
}
