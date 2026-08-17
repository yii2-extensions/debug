<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Panel\Router\RouterSnapshot;
use Yii;
use yii\debug\models\router\{ActionRoutes, CurrentRoute, RouterRules};
use yii\debug\Panel;

/**
 * Renders the routing trace captured by the Router collector.
 *
 * Presents the rules-tested table, the URL-rules table, and the action-routes table side by side; data acquisition
 * lives in {@see \yii\debug\collectors\RouterCollector}.
 */
class RouterPanel extends Panel
{
    protected const string ICON = 'router';
    protected const string NAME = 'Router';

    private RouterSnapshot|null $snapshot = null;

    /**
     * Renders the detail view with the Current Route, Router Rules, and Action Routes tabs.
     */
    #[Override]
    public function getDetail(): string
    {
        return Yii::$app->view->render(
            'panels/router/detail',
            [
                'actionRoutes' => new ActionRoutes(),
                'currentRoute' => CurrentRoute::fromSnapshot($this->snapshot),
                'routerRules' => new RouterRules(),
            ],
            $this,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[Override]
    public function hydrate(array $payload): void
    {
        $this->snapshot = RouterSnapshot::fromArray($payload, "$.panels.{$this->id}");
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
