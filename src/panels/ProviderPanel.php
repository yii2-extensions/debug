<?php

declare(strict_types=1);

namespace yii\debug\panels;

use LogicException;
use PHPForge\Debug\{Panel as PortablePanel, PanelView};
use PHPForge\Debug\Panel\PanelRenderer;
use yii\base\InvalidConfigException;
use yii\debug\exception\Message;
use yii\debug\Panel;

/**
 * Adapts any provider-owned declarative panel to the Yii2 debugger; no framework-specific presenter is needed.
 */
class ProviderPanel extends Panel
{
    /**
     * Declarative panel supplying the identity, capture format, and presentation of this registration.
     */
    public PortablePanel|null $provider = null;

    /**
     * Presentation built by the provider for the hydrated capture, or `null` before hydration.
     */
    private PanelView|null $view = null;

    /**
     * Renders the provider's presentation of the hydrated capture.
     *
     * @throws LogicException when no capture has been hydrated.
     *
     * @return string Rendered detail view.
     */
    public function getDetail(): string
    {
        return PanelRenderer::render(
            $this->getName(),
            $this->view ?? throw new LogicException(Message::PORTABLE_PANEL_NOT_HYDRATED->value)
        );
    }

    /**
     * Returns the human-readable panel name.
     *
     * @throws InvalidConfigException when no provider is configured, or its ID does not match the registration.
     *
     * @return string Panel display name declared by the provider.
     */
    public function getName(): string
    {
        return $this->provider()->name();
    }

    /**
     * Returns the shared Debug Core icon key.
     *
     * @throws InvalidConfigException when no provider is configured, or its ID does not match the registration.
     *
     * @return string Toolbar icon key declared by the provider.
     */
    public function getToolbarIcon(): string
    {
        return $this->provider()->icon();
    }

    /**
     * Returns whether the capture holds a presentation for this panel.
     *
     * An extension the application enabled stays listed even on a capture where it recorded no activity, so its own
     * empty state explains the idle capture instead of the entry disappearing from the sidebar.
     *
     * @return bool `true` when the panel carries an error or a presentation built from the capture; `false` otherwise.
     */
    public function hasContent(): bool
    {
        return $this->hasError() || $this->view !== null;
    }

    /**
     * Clears any previous error and rebuilds the presentation from the captured payload.
     *
     * @param array<string, mixed> $payload Captured panel payload.
     *
     * @throws InvalidConfigException when no provider is configured, or its ID does not match the registration.
     */
    public function hydrate(array $payload): void
    {
        $this->error = null;
        $this->view = null;

        $this->view = $this->provider()->present($payload);
    }

    /**
     * Validates the configured provider as soon as the panel is bound to the module, so misconfiguration
     * surfaces at bootstrap instead of at render time.
     *
     * @throws InvalidConfigException when no provider is configured, or its ID does not match the registration.
     */
    public function moduleBound(): void
    {
        $this->provider();
    }

    /**
     * Builds the toolbar metrics the provider declares for a capture.
     *
     * @return array<int, array<string, mixed>> Toolbar chips built from the provider's metrics, in declared order.
     */
    protected function getToolbarItems(): array
    {
        $items = [];

        foreach ($this->view?->toolbarMetrics() ?? [] as $metric) {
            $items[] = ['title' => $metric['label'], 'value' => $metric['value']['value']];
        }

        return $items;
    }

    /**
     * Returns the configured provider after checking that its ID matches the registration.
     *
     * @throws InvalidConfigException when no provider is configured, or its ID does not match the registration.
     *
     * @return PortablePanel Validated provider backing this panel.
     */
    private function provider(): PortablePanel
    {
        if ($this->provider === null) {
            throw new InvalidConfigException(
                'A declarative debug panel provider must be configured.',
            );
        }

        if ($this->provider->id() !== $this->id) {
            throw new InvalidConfigException(
                Message::PROVIDER_ID_MISMATCH->getMessage('panel'),
            );
        }

        return $this->provider;
    }
}
