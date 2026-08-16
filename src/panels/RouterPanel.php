<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Override;
use PHPForge\Debug\Helper\Coerce;
use Yii;
use yii\base\InlineAction;
use yii\debug\models\router\{ActionRoutes, CurrentRoute, RouterRules};
use yii\debug\Panel;
use yii\debug\panels\router\RouterSnapshot;
use yii\log\Logger;

use function is_array;

/**
 * Captures the routing trace of the request and renders it in the Router panel.
 *
 * Records the URL-rule resolution log emitted by the URL manager (and any REST / Composite / per-rule subclasses), the
 * resolved route, and the dispatched action, so the detail view can show the rules-tested table, the URL-rules table,
 * and the action-routes table side by side.
 */
class RouterPanel extends Panel
{
    protected const string ICON = 'router';
    protected const string NAME = 'Router';

    /**
     * @var array<int, string> Log categories scanned for routing trace messages; consumed by the Logs and Dump panels
     * to exclude the routing chatter from their captures.
     */
    private array $categories = [
        'yii\rest\UrlRule::parseRequest',
        'yii\web\CompositeUrlRule::parseRequest',
        'yii\web\UrlManager::parseRequest',
        'yii\web\UrlRule::parseRequest',
    ];
    private RouterSnapshot|null $snapshot = null;

    /**
     * Snapshots the routing trace, the resolved route, and the dispatched action.
     */
    public function capture(): RouterSnapshot
    {
        $requestedAction = Yii::$app->requestedAction;

        if ($requestedAction === null) {
            $action = null;
        } elseif ($requestedAction instanceof InlineAction && $requestedAction->controller !== null) {
            $action = $requestedAction->controller::class . '::' . $requestedAction->actionMethod . '()';
        } else {
            $action = $requestedAction::class . '::run()';
        }

        return RouterSnapshot::capture(
            $action,
            $this->getLogMessages(Logger::LEVEL_TRACE, $this->categories),
            $requestedAction !== null ? $requestedAction->getUniqueId() : Yii::$app->requestedRoute,
        );
    }

    /**
     * Returns the log categories scanned for routing trace messages.
     *
     * @return array<int, string> Category names in declaration order.
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

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
     * Appends one or more log categories to {@see $categories}.
     *
     * @param array<int, string>|string $values Single category, or list of categories.
     */
    public function setCategories(array|string $values): void
    {
        if (!is_array($values)) {
            $values = [$values];
        }

        $this->categories = [...$this->categories, ...Coerce::stringList($values)];
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
