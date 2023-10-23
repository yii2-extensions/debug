<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\panels;

use Yii;
use yii\base\InlineAction;
use yii\debug\models\router\ActionRoutes;
use yii\debug\models\router\CurrentRoute;
use yii\debug\models\router\RouterRules;
use yii\debug\Panel;
use yii\log\Logger;

use function array_merge;
use function get_class;
use function is_array;

/**
 * RouterPanel provides a panel which displays information about routing process.
 *
 * @property array $categories Note that the type of this property differs in getter and setter.
 * See [[getCategories()]] and [[setCategories()]] for details.
 *
 * @author Dmitriy Bashkarev <dmitriy@bashkarev.com>
 * @since 2.0.8
 */
class RouterPanel extends Panel
{
    private array $_categories = [
        'yii\web\UrlManager::parseRequest',
        'yii\web\UrlRule::parseRequest',
        'yii\web\CompositeUrlRule::parseRequest',
        'yii\rest\UrlRule::parseRequest',
    ];

    public function setCategories(array|string $values): void
    {
        if (!is_array($values)) {
            $values = [$values];
        }
        $this->_categories = array_merge($this->_categories, $values);
    }

    /**
     * Listens to categories of the messages.
     */
    public function getCategories(): array
    {
        return $this->_categories;
    }

    public function getName(): string
    {
        return 'Router';
    }

    public function getSummary(): string
    {
        return Yii::$app->view->render('panels/router/summary', ['panel' => $this]);
    }

    public function getDetail(): string
    {
        return Yii::$app->view->render('panels/router/detail', [
            'currentRoute' => new CurrentRoute($this->data),
            'routerRules' => new RouterRules(),
            'actionRoutes' => new ActionRoutes(),
        ]);
    }

    public function save(): mixed
    {
        if (Yii::$app->requestedAction) {
            if (Yii::$app->requestedAction instanceof InlineAction) {
                $action = get_class(Yii::$app->requestedAction->controller) . '::' . Yii::$app->requestedAction->actionMethod . '()';
            } else {
                $action = get_class(Yii::$app->requestedAction) . '::run()';
            }
        } else {
            $action = null;
        }
        return [
            'messages' => $this->getLogMessages(Logger::LEVEL_TRACE, $this->_categories),
            'route' => Yii::$app->requestedAction ? Yii::$app->requestedAction->getUniqueId() : Yii::$app->requestedRoute,
            'action' => $action,
        ];
    }
}
