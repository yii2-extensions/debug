<?php

declare(strict_types=1);

namespace yii\debug;

use Yii;

use function in_array;
use function is_float;
use function is_int;
use function is_string;

/**
 * Shared default configuration for GridView widgets rendered inside the debug panel UI.
 *
 * Centralizes the pager markup, table classes, and row-status helpers so every `GridView::widget(...)` call in the
 * debug views emits consistent, namespaced, framework-agnostic markup.
 */
final class GridViewConfig
{
    /**
     * Returns the default GridView options for the debug UI.
     *
     * @return array{
     *     tableOptions: array{class: string},
     *     options: array{class: string},
     *     layout: string,
     *     summaryOptions: array{class: string},
     *     pager: array{
     *         options: array{class: string},
     *         linkContainerOptions: array{class: string},
     *         linkOptions: array{class: string},
     *         disabledListItemSubTagOptions: array{tag: string, class: string},
     *         activePageCssClass: string,
     *         disabledPageCssClass: string,
     *     },
     * }
     */
    public static function defaults(): array
    {
        return [
            'tableOptions' => ['class' => 'yii-debug-table'],
            'options' => ['class' => 'yii-debug-grid'],
            // Move the row count below the table and align it right; the panel-level
            // `<header class="yii-debug-grid-summary">` already carries the meaningful
            // summary above the table — and the page-size selector now lives inside that
            // header (rendered via `pageSizeSelectorHtml()` from each panel view), so the
            // footer is just a row-count hint plus the pager.
            'layout' => "{items}\n<div class=\"yii-debug-grid-footer\">{summary}\n{pager}\n</div>",
            'summaryOptions' => ['class' => 'summary yii-debug-grid-count'],
            'pager' => [
                'options' => ['class' => 'yii-debug-pager'],
                'linkContainerOptions' => ['class' => 'yii-debug-pager-item'],
                'linkOptions' => ['class' => 'yii-debug-pager-link'],
                'disabledListItemSubTagOptions' => [
                    'tag' => 'span',
                    'class' => 'yii-debug-pager-link',
                ],
                'activePageCssClass' => 'is-active',
                'disabledPageCssClass' => 'is-disabled',
            ],
        ];
    }

    /**
     * Returns the inline page-size selector markup rendered inside the GridView footer.
     *
     * The dropdown lets the user switch between 25 / 50 / 100 / All rows per page. JavaScript wired in `debug.js`
     * picks up the change event, rewrites the `per-page` query param and reloads the panel — keeping every other
     * filter/sort param intact.
     *
     * @since 2.1.30
     */
    public static function pageSizeSelectorHtml(): string
    {
        $current = self::currentPageSize();
        $options = ['10', '25', '50', '100', 'all'];
        $items = '';

        foreach ($options as $value) {
            $selected = $value === $current ? ' selected' : '';
            $label = $value === 'all' ? 'All' : $value;
            $items .= '<option value="' . $value . '"' . $selected . '>' . $label . '</option>';
        }

        return '<label class="yii-debug-grid-pagesize">'
            . '<span class="yii-debug-grid-pagesize-label">Rows</span>'
            . '<select class="yii-debug-grid-pagesize-select" data-yii-debug-pagesize>'
            . $items
            . '</select>'
            . '</label>';
    }

    /**
     * Returns a pagination config keyed off the `per-page` query parameter.
     *
     * Reads `Yii::$app->request->get('per-page')` and translates it to a Yii pagination array. The literal string
     * `"all"` (case-insensitive) disables pagination entirely. Numeric values within sensible bounds are honoured;
     * anything else falls back to `$default`.
     *
     * @param int $default Page size used when no `per-page` param is supplied.
     *
     * @return array<string, mixed>|false `false` when "all" was requested, otherwise a pagination config.
     */
    public static function paginationFromRequest(int $default = 50): array|false
    {
        $raw = self::queryParamString('per-page');

        if ($raw !== null && strcasecmp($raw, 'all') === 0) {
            return false;
        }

        $size = $raw !== null && is_numeric($raw) ? (int) $raw : $default;
        if ($size <= 0) {
            $size = $default;
        }

        return [
            'pageSize' => min($size, 1000),
            'pageSizeParam' => 'per-page',
            'pageSizeLimit' => false,
        ];
    }

    /**
     * Returns the row-options array for a given status level.
     *
     * Accepts any of: `success`, `info`, `warning`, `danger`, `error` (aliases to `danger`).
     *
     * @param string|null $level Status keyword.
     *
     * @return array<string, mixed> Returns an empty array for unknown/`null` levels.
     */
    public static function rowClassFor(string|null $level): array
    {
        if ($level === null || $level === '') {
            return [];
        }

        $normalized = $level === 'error' ? 'danger' : $level;

        if (!in_array($normalized, ['success', 'info', 'warning', 'danger'], true)) {
            return [];
        }

        return ['class' => 'yii-debug-row-' . $normalized];
    }

    private static function currentPageSize(): string
    {
        $raw = self::queryParamString('per-page');
        return $raw ?? '50';
    }

    /**
     * Reads a query parameter as a string or returns `null` when absent / non-scalar / no web request available.
     */
    private static function queryParamString(string $name): string|null
    {
        $app = Yii::$app;

        $value = $app->getRequest()->getQueryParam($name);

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }
}
