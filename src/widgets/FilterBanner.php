<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\widgets;

use Yii;
use yii\base\Model;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * Renders the active-filter banner above a panel's GridView.
 *
 * The banner surfaces every `<FormName>[<attr>]` query param currently applied to the page as
 * a removable pill, plus a "Clear all" action. Removal links rebuild the current URL minus the
 * targeted param(s) — every other query param (sort, page, theme, etc.) is preserved so the
 * developer keeps their context.
 *
 * Usage example:
 * ```php
 * <?= \yii\debug\widgets\FilterBanner::widget(['searchModel' => $searchModel]) ?>
 * ```
 *
 * @since 2.2
 */
class FilterBanner extends Widget
{
    /**
     * The search model whose `formName()` defines the query-param prefix to scan
     * (e.g., `Debug`, `Log`, `Db`, `Profile`, `Event`, `Mail`, `User`).
     *
     * @var Model
     */
    public Model $searchModel;

    /**
     * Returns rendered banner HTML, or empty string when no filters are active.
     */
    public function run(): string
    {
        $formName = $this->searchModel->formName();
        $request = Yii::$app->getRequest();
        $rawFilters = (array) $request->get($formName, []);

        $activeFilters = [];
        foreach ($rawFilters as $attr => $val) {
            if ($val === '' || $val === null) {
                continue;
            }
            if (!is_string($attr) || !is_scalar($val)) {
                continue;
            }
            $activeFilters[$attr] = (string) $val;
        }

        if ($activeFilters === []) {
            return '';
        }

        $count = count($activeFilters);
        $pills = '';
        foreach ($activeFilters as $attr => $val) {
            $pills .= Html::a(
                Html::tag('span', Html::encode($attr), ['class' => 'yii-debug-active-filter-attr'])
                . Html::tag('span', ':', ['class' => 'yii-debug-active-filter-sep'])
                . Html::tag('span', Html::encode($val), ['class' => 'yii-debug-active-filter-value'])
                . Html::tag('span', '×', ['class' => 'yii-debug-active-filter-x', 'aria-hidden' => 'true']),
                $this->buildUrl($formName, [$attr]),
                ['class' => 'yii-debug-active-filter-pill', 'title' => 'Remove this filter'],
            );
        }

        return Html::tag(
            'div',
            Html::tag(
                'span',
                $count . ' filter' . ($count === 1 ? '' : 's') . ' active',
                ['class' => 'yii-debug-active-filters-label'],
            )
            . Html::tag('span', $pills, ['class' => 'yii-debug-active-filters-list'])
            . Html::a('Clear all', $this->buildUrl($formName, array_keys($activeFilters)), [
                'class' => 'yii-debug-active-filters-clear',
                'title' => 'Clear all filters and show every row',
            ]),
            ['class' => 'yii-debug-active-filters', 'role' => 'group', 'aria-label' => 'Active filters'],
        );
    }

    /**
     * Builds a URL for the current route, preserving every existing query param except the listed
     * `<FormName>[<attr>]` slots and the `page` cursor (so removing a filter always lands on page
     * one).
     *
     * @param string $formName Search model's form name (the param prefix to manipulate).
     * @param array $without Attribute names whose `<FormName>[<attr>]` slot should be dropped.
     */
    private function buildUrl(string $formName, array $without): string
    {
        $params = Yii::$app->getRequest()->getQueryParams();
        $bag = is_array($params[$formName] ?? null) ? $params[$formName] : [];

        foreach ($without as $attr) {
            unset($bag[$attr]);
        }

        if ($bag === []) {
            unset($params[$formName]);
        } else {
            $params[$formName] = $bag;
        }

        unset($params['page']);

        $route = '/' . Yii::$app->controller->getRoute();
        array_unshift($params, $route);

        return Url::to($params);
    }
}
