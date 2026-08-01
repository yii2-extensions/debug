<?php

declare(strict_types=1);

namespace yii\debug\widgets;

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\List\{Li, Ul};
use UIAwesome\Html\Palpable\A;

/**
 * Renders the shared accessible tab pattern used by debug panels.
 */
final class Tabs
{
    /**
     * @param non-empty-string $id
     * @param non-empty-string $ariaLabel
     * @param list<array{label: string, content: string}> $tabs
     */
    public static function render(string $id, string $ariaLabel, array $tabs): string
    {
        $items = [];
        $panels = [];

        foreach ($tabs as $index => $tab) {
            $active = $index === 0;
            $tabId = "{$id}-tab-{$index}";
            $panelId = "{$id}-panel-{$index}";

            $items[] = Li::tag()
                ->class('yii-debug-tab')
                ->addAttribute('role', 'presentation')
                ->html(
                    A::tag()
                        ->id($tabId)
                        ->addAriaAttribute('controls', $panelId)
                        ->addAriaAttribute('selected', $active ? 'true' : 'false')
                        ->addAttribute('data-yii-debug-toggle', 'tab')
                        ->addAttribute('role', 'tab')
                        ->addAttribute('tabindex', $active ? '0' : '-1')
                        ->class($active ? 'yii-debug-tab-link is-active' : 'yii-debug-tab-link')
                        ->content($tab['label'])
                        ->href("#{$panelId}"),
                );

            $panel = Div::tag()
                ->id($panelId)
                ->addAriaAttribute('labelledby', $tabId)
                ->addAttribute('role', 'tabpanel')
                ->class($active ? 'yii-debug-tab-panel is-active' : 'yii-debug-tab-panel')
                ->html($tab['content']);

            if (!$active) {
                $panel = $panel->addAttribute('hidden', true);
            }

            $panels[] = $panel;
        }

        return Ul::tag()
            ->class('yii-debug-tabs')
            ->addAriaAttribute('label', $ariaLabel)
            ->addAttribute('role', 'tablist')
            ->html(...$items)
            ->render()
            . Div::tag()
                ->class('yii-debug-tab-content')
                ->html(...$panels)
                ->render();
    }
}
