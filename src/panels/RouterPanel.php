<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Stringable;
use Yii;
use yii\base\InlineAction;
use yii\debug\models\router\{ActionRoutes, CurrentRoute, RouterRules};
use yii\debug\Panel;
use yii\log\Logger;

use function get_class;
use function is_array;
use function is_scalar;
use function is_string;

/**
 * RouterPanel provides a panel which displays information about routing process.
 */
class RouterPanel extends Panel
{
    /**
     * @var array<int, string>
     */
    private array $categories = [
        'yii\rest\UrlRule::parseRequest',
        'yii\web\CompositeUrlRule::parseRequest',
        'yii\web\UrlManager::parseRequest',
        'yii\web\UrlRule::parseRequest',
    ];

    /**
     * Listens categories of the messages.
     *
     * @return array<int, string>
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    public function getDetail(): string
    {
        return Yii::$app->view->render(
            'panels/router/detail',
            [
                'actionRoutes' => new ActionRoutes(),
                'currentRoute' => new CurrentRoute($this->getRouteData()),
                'routerRules' => new RouterRules(),
            ],
        );
    }

    public function getName(): string
    {
        return 'Router';
    }

    public function getSummary(): string
    {
        return Yii::$app->view->render(
            'panels/router/summary',
            ['panel' => $this],
        );
    }

    public function getToolbarIcon(): string
    {
        return 'router';
    }

    /**
     * @return array{messages: array<int, array<int|string, mixed>>, route: string, action: string|null}
     */
    public function save(): array
    {
        $requestedAction = Yii::$app->requestedAction;

        if ($requestedAction !== null) {
            if ($requestedAction instanceof InlineAction) {
                $action = get_class($requestedAction->controller) . '::' . $requestedAction->actionMethod . '()';
            } else {
                $action = get_class($requestedAction) . '::run()';
            }
        } else {
            $action = null;
        }

        return [
            'action' => $action,
            'messages' => $this->getLogMessages(Logger::LEVEL_TRACE, $this->categories),
            'route' => $requestedAction !== null ? $requestedAction->getUniqueId() : Yii::$app->requestedRoute,
        ];
    }

    /**
     * @param array<int, string>|string $values
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
     * @return array<int, array<string, mixed>>
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
     * @return array{messages: array<int, array<int|string, mixed>>, route: string, action: string|null}
     */
    private function getRouteData(): array
    {
        $data = is_array($this->data) ? $this->data : [];

        return [
            'action' => self::stringValue($data['action'] ?? null),
            'messages' => self::normalizeMessages($data['messages'] ?? []),
            'route' => self::stringValue($data['route'] ?? null) ?? '',
        ];
    }

    /**
     * @param mixed $messages Raw saved route log messages.
     *
     * @return array<int, array<int|string, mixed>>
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
     * @param array<int|string, mixed> $values
     *
     * @return array<int, string>
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

    private static function stringValue(mixed $value): string|null
    {
        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return null;
    }
}
