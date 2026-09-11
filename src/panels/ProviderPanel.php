<?php

declare(strict_types=1);

namespace yii\debug\panels;

use LogicException;
use PHPForge\Debug\{Panel as PortablePanel, PanelView};
use PHPForge\Debug\Panel\PanelRenderer;
use yii\base\InvalidConfigException;
use yii\debug\Panel;

/**
 * Adapts any provider-owned declarative panel to the Yii2 debugger; no framework-specific presenter is needed.
 */
class ProviderPanel extends Panel
{
    public PortablePanel|null $provider = null;
    private PanelView|null $view = null;

    public function getDetail(): string
    {
        return PanelRenderer::render($this->getName(), $this->view ?? throw new LogicException('No portable panel capture has been hydrated.'));
    }

    public function getName(): string
    {
        return $this->provider()->name();
    }

    public function getToolbarIcon(): string
    {
        return $this->provider()->icon();
    }

    public function hasContent(): bool
    {
        return $this->hasError() || ($this->view?->isActive() ?? false);
    }

    public function hydrate(array $payload): void
    {
        $this->error = null;
        $this->view = null;
        $this->view = $this->provider()->present($payload);
    }

    public function moduleBound(): void
    {
        $this->provider();
    }

    protected function getToolbarItems(): array
    {
        $items = [];
        foreach ($this->view?->toolbarMetrics() ?? [] as $metric) {
            $items[] = ['title' => $metric['label'], 'value' => $metric['value']['value']];
        }
        return $items;
    }

    private function provider(): PortablePanel
    {
        if ($this->provider === null) {
            throw new InvalidConfigException('A declarative debug panel provider must be configured.');
        }
        if ($this->provider->id() !== $this->id) {
            throw new InvalidConfigException('The debug panel registration ID must match its provider.');
        }
        return $this->provider;
    }
}
