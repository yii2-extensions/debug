<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Panel\Vite\{ViteComponent, ViteSectionRenderer, ViteSnapshot, ViteSummary};
use yii\debug\Panel;

/**
 * Renders the Vite integrations captured by the Vite collector.
 *
 * Presents each loaded Vite component's runtime mode, entry points, build settings, and emitted chunks; data
 * acquisition lives in {@see \yii\debug\collectors\ViteCollector}.
 */
class VitePanel extends Panel
{
    protected const string ICON = 'brand-javascript';
    protected const string NAME = 'Vite';

    private ViteSnapshot|null $snapshot = null;

    /**
     * @return list<ViteComponent> Captured Vite components in load order.
     */
    public function getComponents(): array
    {
        return $this->snapshot?->components() ?? [];
    }

    /**
     * Renders the detail view from the captured Vite component snapshots.
     */
    #[Override]
    public function getDetail(): string
    {
        return ViteSectionRenderer::render($this->summary());
    }

    /**
     * Returns whether the loaded capture contains at least one Vite component.
     */
    #[Override]
    public function hasContent(): bool
    {
        return $this->getComponents() !== [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[Override]
    public function hydrate(array $payload): void
    {
        $this->snapshot = ViteSnapshot::fromArray($payload, "$.panels.{$this->id}");
    }

    /**
     * Returns a concise runtime-mode chip, or `[]` when no Vite components were captured.
     */
    #[Override]
    protected function getToolbarItems(): array
    {
        $summary = $this->summary();

        if ($summary->isEmpty()) {
            return [];
        }

        $count = $summary->count();
        $modeLabel = $summary->modeLabel();

        return [
            [
                'title' => 'Vite mode',
                'value' => $count === 1 ? $modeLabel : "{$count} components · {$modeLabel}",
            ],
        ];
    }

    private function summary(): ViteSummary
    {
        return new ViteSummary($this->getComponents());
    }
}
