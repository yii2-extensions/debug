<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Yii;
use yii\base\InlineAction;
use yii\debug\helpers\Coerce;
use yii\debug\models\router\{ActionRoutes, CurrentRoute, RouterRules};
use yii\debug\Panel;
use yii\log\Logger;

use function is_array;
use function is_string;

/**
 * Captures the routing trace of the request and renders it in the Router panel.
 *
 * Records the URL-rule resolution log emitted by the URL manager (and any REST / Composite / per-rule subclasses), the
 * resolved route, and the dispatched action, so the detail view can show the rules-tested table, the URL-rules table,
 * and the action-routes table side by side.
 *
 * @extends Panel<array{
 *   action: string|null,
 *   messages: array<int, array<int|string, mixed>>,
 *   route: string,
 * }>
 */
class RouterPanel extends Panel
{
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
    public function getDetail(): string
    {
        return Yii::$app->view->render(
            'panels/router/detail',
            [
                'actionRoutes' => new ActionRoutes(),
                'currentRoute' => new CurrentRoute($this->getRouteData()),
                'routerRules' => new RouterRules(),
            ],
            $this,
        );
    }

    /**
     * Returns the panel display name.
     */
    public function getName(): string
    {
        return 'Router';
    }

    /**
     * Renders the toolbar summary chip.
     */
    public function getSummary(): string
    {
        return Yii::$app->view->render(
            'panels/router/summary',
            ['panel' => $this],
            $this,
        );
    }

    /**
     * Returns the toolbar icon name.
     */
    public function getToolbarIcon(): string
    {
        return 'router';
    }

    /**
     * Snapshots the routing trace, the resolved route, and the dispatched action.
     *
     * @return array{messages: array<int, array<int|string, mixed>>, route: string, action: string|null} Captured
     * payload consumed by {@see getRouteData()} on read-back.
     */
    public function save(): array
    {
        $requestedAction = Yii::$app->requestedAction;

        if ($requestedAction === null) {
            $action = null;
        } elseif ($requestedAction instanceof InlineAction && $requestedAction->controller !== null) {
            $action = $requestedAction->controller::class . '::' . $requestedAction->actionMethod . '()';
        } else {
            $action = $requestedAction::class . '::run()';
        }

        return [
            'action' => $action,
            'messages' => $this->getLogMessages(Logger::LEVEL_TRACE, $this->categories),
            'route' => $requestedAction !== null ? $requestedAction->getUniqueId() : Yii::$app->requestedRoute,
        ];
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

        $this->categories = [
            ...$this->categories,
            ...self::normalizeStringList($values),
        ];
    }

    /**
     * Builds the toolbar item with the resolved route as the value and the dispatched action in the tooltip.
     *
     * @return array<int, array<string, mixed>> Single-element list with the route chip.
     */
    protected function getToolbarItems(): array
    {
        $data = $this->getRouteData();

        return [
            [
                'title' => 'Action: ' . ($data['action'] ?? ''),
                'value' => $data['route'],
            ],
        ];
    }

    /**
     * Narrows the saved panel data into the typed `messages` / `route` / `action` shape consumed by the renderers.
     *
     * @return array{messages: array<int, array<int|string, mixed>>, route: string, action: string|null} Normalized
     * payload with defensible defaults (`''` / `null` / `[]`) for missing fields.
     */
    private function getRouteData(): array
    {
        $data = is_array($this->data) ? $this->data : [];

        return [
            'action' => Coerce::stringOrNull($data['action'] ?? null),
            'messages' => self::normalizeMessages($data['messages'] ?? []),
            'route' => Coerce::stringOrNull($data['route'] ?? null) ?? '',
        ];
    }

    /**
     * Filters the raw saved messages to keep only array entries.
     *
     * @param mixed $messages Raw saved route log messages.
     *
     * @return array<int, array<int|string, mixed>> Reindexed list of message arrays.
     */
    private static function normalizeMessages(mixed $messages): array
    {
        if (!is_array($messages)) {
            return [];
        }

        $normalized = [];

        foreach ($messages as $message) {
            if (is_array($message)) {
                $normalized[] = $message;
            }
        }

        return $normalized;
    }

    /**
     * Filters the input list to keep only string entries.
     *
     * @param array<int|string, mixed> $values Raw category list.
     *
     * @return array<int, string> String entries in original order, possibly empty.
     */
    private static function normalizeStringList(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            if (is_string($value)) {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }
}
