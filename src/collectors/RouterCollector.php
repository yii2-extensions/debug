<?php

declare(strict_types=1);

namespace yii\debug\collectors;

use PHPForge\Debug\Panel\Router\RouterSnapshot;
use Yii;
use yii\base\InlineAction;
use yii\log\Logger;

use function is_array;

/**
 * Captures the routing trace of the request for the Router panel.
 *
 * Records the URL-rule resolution log emitted by the URL manager (and any REST / Composite / per-rule subclasses), the
 * resolved route, and the dispatched action.
 *
 * Usage example:
 *
 * ```php
 * $categories = (new \yii\debug\collectors\RouterCollector())->getCategories();
 * ```
 */
class RouterCollector extends Collector
{
    /**
     * @var list<string> Log categories scanned for routing trace messages; consumed by the Logs and Dump
     * collectors to exclude the routing chatter from their captures.
     */
    private array $categories = [
        'yii\rest\UrlRule::parseRequest',
        'yii\web\CompositeUrlRule::parseRequest',
        'yii\web\UrlManager::parseRequest',
        'yii\web\UrlRule::parseRequest',
    ];

    /**
     * Snapshots the routing trace, the resolved route, and the dispatched action.
     *
     * @return RouterSnapshot|null Captured routing payload; `null` when the collector never started.
     */
    public function capture(): RouterSnapshot|null
    {
        if (!$this->isStarted()) {
            return null;
        }

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
     * Usage example:
     *
     * ```php
     * $categories = $collector->getCategories();
     * ```
     *
     * @return list<string> Category names in declaration order.
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /**
     * Returns the stable ID pairing this collector with the Router panel.
     *
     * Usage example:
     *
     * ```php
     * $id = (new \yii\debug\collectors\RouterCollector())->id();
     * ```
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'router';
    }

    /**
     * Appends one or more log categories to {@see $categories}.
     *
     * Usage example:
     *
     * ```php
     * $collector->setCategories('app\components\UrlRule::parseRequest');
     * ```
     *
     * @param list<string>|string $values Single category, or list of categories.
     */
    public function setCategories(array|string $values): void
    {
        if (!is_array($values)) {
            $values = [$values];
        }

        $this->categories = [...$this->categories, ...$values];
    }
}
