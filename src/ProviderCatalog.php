<?php

declare(strict_types=1);

namespace yii\debug;

use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\CollectorInterface;

use function array_values;

/**
 * Lists the optional providers the debugger wires on its own, each one as soon as its package is installed.
 *
 * Provider classes are written as plain strings, so this package neither imports nor requires the optional packages
 * shipping them. An application drops one through the `enabled` option of its `collectors` and `panels` entries.
 */
final readonly class ProviderCatalog
{
    /**
     * @var list<PackagedProvider> Providers in registration order.
     */
    private array $providers;

    /**
     * @param PackagedProvider ...$providers Providers in registration order.
     */
    public function __construct(PackagedProvider ...$providers)
    {
        $this->providers = array_values($providers);
    }

    /**
     * Returns the collector definitions of every installed provider, keyed by stable ID.
     *
     * @param CapturePolicy $capturePolicy Host redaction policy handed to a collector that takes one.
     *
     * @return array<string, array<string, mixed>|class-string<CollectorInterface>> Collector definitions indexed by
     * provider ID.
     */
    public function collectors(CapturePolicy $capturePolicy): array
    {
        $collectors = [];

        foreach ($this->installed() as $provider) {
            $collectors[$provider->id] = $provider->collectorDefinition($capturePolicy);
        }

        return $collectors;
    }

    /**
     * Returns whether the catalog declares `$id`, whether or not its package is installed.
     *
     * @param string $id Stable provider ID.
     *
     * @return bool `true` when the catalog owns the ID; `false` otherwise.
     */
    public function has(string $id): bool
    {
        return $this->provider($id) !== null;
    }

    /**
     * Filters the catalog down to the providers whose package the application installed.
     *
     * @return list<PackagedProvider> Installed providers in registration order.
     */
    public function installed(): array
    {
        $installed = [];

        foreach ($this->providers as $provider) {
            if ($provider->installed()) {
                $installed[] = $provider;
            }
        }

        return $installed;
    }

    /**
     * Returns whether the provider declared under `$id` has its package installed.
     *
     * @param string $id Stable provider ID.
     *
     * @return bool `true` when the catalog owns the ID and its package is installed; `false` otherwise.
     */
    public function isInstalled(string $id): bool
    {
        return $this->provider($id)?->installed() ?? false;
    }

    /**
     * Returns the catalog of providers this package ships, in registration order.
     *
     * @return self Catalog holding the Inertia provider first and the Vite provider second.
     */
    public static function packaged(): self
    {
        return new self(
            new PackagedProvider(
                'inertia',
                'PHPForge\Inertia\Debug\InertiaCollector',
                'PHPForge\Inertia\Debug\InertiaPanel',
                'inertia',
                'yii\inertia\Manager',
                ProviderAttachment::Property,
                // The packaged Inertia collector redacts page props and URLs with the policy the Request panel applies.
                static fn(CapturePolicy $capturePolicy): array => [
                    $capturePolicy->redact(...),
                    $capturePolicy->redactUrl(...),
                ],
            ),
            new PackagedProvider(
                'vite',
                'PHPForge\Vite\Debug\ViteCollector',
                'PHPForge\Vite\Debug\VitePanel',
                'vite',
                'PHPForge\Vite\Vite',
                ProviderAttachment::Constructor,
            ),
        );
    }

    /**
     * Returns the panel classes of every installed provider, keyed by stable ID.
     *
     * @return array<string, string> Panel classes indexed by provider ID.
     */
    public function panels(): array
    {
        $panels = [];

        foreach ($this->installed() as $provider) {
            $panels[$provider->id] = $provider->panel;
        }

        return $panels;
    }

    /**
     * Returns the provider the catalog declares under `$id`.
     *
     * @param string $id Stable provider ID.
     *
     * @return PackagedProvider|null Declared provider, or `null` when the catalog owns no such ID.
     */
    public function provider(string $id): PackagedProvider|null
    {
        foreach ($this->providers as $provider) {
            if ($provider->id === $id) {
                return $provider;
            }
        }

        return null;
    }
}
