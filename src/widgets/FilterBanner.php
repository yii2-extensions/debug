<?php

declare(strict_types=1);

namespace yii\debug\widgets;

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use Yii;
use yii\base\{InvalidConfigException, Model, Widget};
use yii\debug\exception\Message;
use yii\helpers\Url;

use function array_fill_keys;
use function array_keys;
use function array_replace_recursive;
use function array_unique;
use function array_unshift;
use function array_values;
use function count;
use function is_scalar;
use function is_string;

/**
 * Renders the active-filter banner above a panel's GridView.
 *
 * The banner surfaces normalized active filters as removable pills, plus a "Clear all" action. By default it derives
 * those filters from scalar `<FormName>[<attr>]` query parameters; callers may supply sanitized values after applying
 * domain validation. Removal links rebuild the current URL minus the targeted parameters while preserving unrelated
 * state such as sort and theme.
 */
class FilterBanner extends Widget
{
    /**
     * Normalized filter values to display, or `null` to derive them from the request.
     *
     * The Clear all link still removes every raw filter key, including submitted values omitted from this map after
     * validation.
     *
     * @var array<string, mixed>|null
     */
    public array|null $activeFilters = null;

    /**
     * The search model whose {@see Model::formName()} defines the query-param prefix to scan (for example, 'Debug',
     * 'Log', 'Db', 'Profile', 'Event', 'Mail', 'User').
     */
    public Model|null $searchModel = null;

    /**
     * Returns rendered banner HTML, or empty string when no filters are active.
     *
     * @throws InvalidConfigException When the widget is instantiated without a `searchModel`.
     */
    public function run(): string
    {
        if ($this->searchModel === null) {
            $class = self::class;

            throw new InvalidConfigException(
                Message::SEARCH_MODEL_REQUIRED->getMessage($class),
            );
        }

        $formName = $this->searchModel->formName();

        $request = Yii::$app->getRequest();

        $rawFilters = (array) $request->get($formName, []);

        $activeFilters = self::normalizeFilters($this->activeFilters ?? $rawFilters);

        if ($activeFilters === []) {
            return '';
        }

        $count = count($activeFilters);

        $pills = '';

        foreach ($activeFilters as $attr => $val) {
            $attribute = Span::tag()
                ->class('yii-debug-active-filter-attr')
                ->content($attr)
                ->render();
            $separator = Span::tag()
                ->class('yii-debug-active-filter-sep')
                ->content(':')
                ->render();
            $value = Span::tag()
                ->class('yii-debug-active-filter-value')
                ->content($val)
                ->render();
            $remove = Span::tag()
                ->class('yii-debug-active-filter-x')
                ->addAttribute('aria-hidden', 'true')
                ->content('×')
                ->render();

            $pillContent = "{$attribute}{$separator}{$value}{$remove}";

            $pills .= A::tag()
                ->class('yii-debug-active-filter-pill')
                ->addAriaAttribute('label', "Remove {$attr}: {$val} filter")
                ->addAttribute('title', 'Remove this filter')
                ->href($this->buildUrl($formName, [$attr]))
                ->html($pillContent)
                ->render();
        }

        $label = Span::tag()
            ->class('yii-debug-active-filters-label')
            ->content($count . ' filter' . ($count === 1 ? '' : 's') . ' active')
            ->render();

        $list = Span::tag()->class('yii-debug-active-filters-list')->html($pills)->render();

        $clearAttributes = array_values(
            array_unique(
                [
                    ...self::attributeNames($rawFilters),
                    ...array_keys($activeFilters),
                ],
            ),
        );

        $clearAll = A::tag()
            ->class('yii-debug-active-filters-clear')
            ->addAriaAttribute('label', 'Clear all active filters')
            ->addAttribute('title', 'Clear all filters and show every row')
            ->href($this->buildUrl($formName, $clearAttributes))
            ->content('Clear all')
            ->render();

        $content = "{$label}{$list}{$clearAll}";

        return Div::tag()
            ->class('yii-debug-active-filters')
            ->addAttribute('role', 'group')
            ->addAriaAttribute('label', 'Active filters')
            ->html($content)
            ->render();
    }

    /**
     * @param array<array-key, mixed> $filters
     *
     * @return list<string>
     */
    private static function attributeNames(array $filters): array
    {
        $names = [];

        foreach (array_keys($filters) as $attribute) {
            if (is_string($attribute)) {
                $names[] = $attribute;
            }
        }

        return $names;
    }

    /**
     * Builds a URL for the current standalone-action route (or the controller route as a fallback), preserving every
     * existing query param except the listed `<FormName>[<attr>]` slots and the `page` cursor (so removing a filter
     * always lands on page one).
     *
     * @param string $formName Search model's form name (the param prefix to manipulate).
     * @param list<string> $without Attribute names whose `<FormName>[<attr>]` slot should be dropped.
     */
    private function buildUrl(string $formName, array $without): string
    {
        $params = [
            $formName => array_fill_keys($without, null),
            'page' => null,
        ];

        if (Yii::$app->controller !== null || Yii::$app->requestedRoute !== null && Yii::$app->requestedRoute !== '') {
            return Url::current($params);
        }

        $params = array_replace_recursive(Yii::$app->getRequest()->getQueryParams(), $params);

        array_unshift($params, '/' . (Yii::$app->requestedAction?->getUniqueId() ?? ''));

        return Url::to($params);
    }

    /**
     * @param array<array-key, mixed> $filters
     *
     * @return array<string, string>
     */
    private static function normalizeFilters(array $filters): array
    {
        $normalized = [];

        foreach ($filters as $attribute => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            if (!is_string($attribute) || !is_scalar($value)) {
                continue;
            }

            $normalized[$attribute] = (string) $value;
        }

        return $normalized;
    }
}
