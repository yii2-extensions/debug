<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Panel\{PanelIcon, PanelRenderer, PanelTitle};
use PHPForge\Debug\Panel\Router\{ActionRouteRow, RouterPanel as RouterPresenter, RouterRuleRow, RouterSnapshot};
use yii\debug\models\router\{ActionRoutes, RouterRules};
use yii\debug\Panel;

/**
 * Renders the routing trace captured by the Router collector.
 *
 * Delegates the detail view to the framework-neutral {@see RouterPresenter}, feeding it the live URL rules and
 * action routes; data acquisition lives in {@see \yii\debug\collectors\RouterCollector}.
 */
class RouterPanel extends Panel
{
    /**
     * Whether Router should retain its standalone toolbar and sidebar entries.
     */
    public bool $standalone = true;

    private RouterSnapshot|null $snapshot = null;

    /**
     * Renders the detail view through the shared declarative presenter.
     *
     * @return string Rendered panel markup.
     */
    #[Override]
    public function getDetail(): string
    {
        $routerRules = new RouterRules();

        $ruleRows = [];

        foreach ($routerRules->rules as $rule) {
            $ruleRows[] = RouterRuleRow::from($rule);
        }

        $actionRows = [];

        $actionRoutes = new ActionRoutes();

        foreach ($actionRoutes->routes as $action => $route) {
            $actionRows[] = ActionRouteRow::from($action, $route);
        }

        $snapshot = $this->snapshot ?? new RouterSnapshot(null, '', null, []);

        $view = (new RouterPresenter())
            ->urlManager($routerRules->prettyUrl, $routerRules->strictParsing, $routerRules->suffix ?? '')
            ->rules($ruleRows)
            ->actionRoutes($actionRows)
            ->present($snapshot->jsonSerialize());

        return PanelRenderer::render(
            $this->getName(),
            $view,
        );
    }

    /**
     * Returns the panel display name from the shared title enum.
     */
    #[Override]
    public function getName(): string
    {
        return PanelTitle::ROUTER->value;
    }

    /**
     * Returns the captured routing snapshot for composition by another panel.
     */
    public function getSnapshot(): RouterSnapshot|null
    {
        return $this->snapshot;
    }

    /**
     * Returns the icon key from the shared panel icon enum.
     */
    #[Override]
    public function getToolbarIcon(): string
    {
        return PanelIcon::ROUTER->value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[Override]
    public function hydrate(array $payload): void
    {
        $this->snapshot = RouterSnapshot::fromArray(
            $payload,
            "$.panels.{$this->id}",
        );
    }

    /**
     * Keeps explicitly configured Router panels standalone while allowing the built-in instance to act as a hidden
     * compatibility data source for Request.
     */
    #[Override]
    public function isVisible(): bool
    {
        return $this->standalone;
    }

    /**
     * Builds the toolbar item with the resolved route as the value and the dispatched action in the tooltip.
     *
     * @return array<int, array<string, mixed>> Single-element list with the route chip.
     */
    #[Override]
    protected function getToolbarItems(): array
    {
        $snapshot = $this->snapshot;

        return [
            [
                'title' => 'Action: ' . ($snapshot === null ? '' : $snapshot->action ?? ''),
                'value' => $snapshot === null ? '' : $snapshot->route,
            ],
        ];
    }
}
