<?php

declare(strict_types=1);

namespace yii\debug;

use PHPForge\Debug\Data\{PageSize, QueryInput};
use Yii;
use yii\debug\widgets\DebugDataColumn;

use function in_array;

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
     * Carries the namespaced `yii-debug-*` CSS classes (so the debug styles never clash with the host application's
     * grid styles), the custom layout that confines the table to a `.yii-debug-table-wrap` scroll container and
     * wraps the summary and pager into a single footer, and the pager's active/disabled CSS modifiers used by the
     * debug stylesheet.
     *
     * @return array{
     *   dataColumnClass: class-string<DebugDataColumn>,
     *   tableOptions: array{class: string},
     *   options: array{class: string},
     *   layout: string,
     *   summaryOptions: array{class: string},
     *   pager: array{
     *     options: array{class: string},
     *     linkContainerOptions: array{class: string},
     *     linkOptions: array{class: string},
     *     disabledListItemSubTagOptions: array{tag: string, class: string},
     *     activePageCssClass: string,
     *     disabledPageCssClass: string,
     *   },
     * } GridView config array ready to splat into `GridView::widget([...])`.
     */
    public static function defaults(): array
    {
        return [
            'dataColumnClass' => DebugDataColumn::class,
            'tableOptions' => ['class' => 'yii-debug-table'],
            'options' => ['class' => 'yii-debug-grid'],
            'layout' => "<div class=\"yii-debug-table-wrap\">{items}</div>\n"
                . "<div class=\"yii-debug-grid-footer\">{summary}\n{pager}\n</div>",
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
     * The dropdown lets the user switch between `10` / `25` / `50` / `100` / `All` rows per page. JavaScript wired in
     * `debug.min.js` picks up the change event, rewrites the `per-page` query param, and reloads the panel while
     * keeping every other filter/sort param intact.
     */
    public static function pageSizeSelectorHtml(): string
    {
        return PageSize::selectorHtml(PageSize::current(self::queryParamString('per-page')));
    }

    /**
     * Returns a pagination config keyed off the `per-page` query parameter.
     *
     * Reads `per-page` from the parsed request query and translates it into a Yii pagination array. The literal string
     * `'all'` (case-insensitive) disables pagination entirely. Numeric values are honored within a hard cap of `1000`
     * rows per page; anything else falls back to `$default`.
     *
     * @param positive-int $default Page size used when no `per-page` param is supplied or the value is invalid.
     *
     * @return array<string, mixed>|false `false` when `'all'` was requested, otherwise a pagination config carrying
     * `pageSize`, `pageSizeParam`, and `pageSizeLimit`.
     */
    public static function paginationFromRequest(int $default = 50): array|false
    {
        $size = PageSize::resolve(self::queryParamString('per-page'), $default);

        if ($size === null) {
            return false;
        }

        return [
            'pageSize' => $size,
            'pageSizeParam' => 'per-page',
            'pageSizeLimit' => false,
        ];
    }

    /**
     * Returns the row-options array carrying the `yii-debug-row-<variant>` CSS class for the given status level.
     *
     * Accepts `success`, `info`, `warning`, `danger`, and `error` (aliased to `danger`). Unknown or empty levels yield
     * an empty array, so the caller can splat the result safely.
     *
     * @param string|null $level Status keyword, or `null` to skip the class.
     *
     * @return array<string, mixed> Row-options array with the `class` key set, or `[]` for unknown/`null` levels.
     */
    public static function rowClassFor(string|null $level): array
    {
        $normalized = $level === 'error' ? 'danger' : $level;

        if (!in_array($normalized, ['success', 'info', 'warning', 'danger'], true)) {
            return [];
        }

        return ['class' => 'yii-debug-row-' . $normalized];
    }

    /**
     * Reads a query parameter as a string, returning `null` when the parameter is absent or non-scalar.
     */
    private static function queryParamString(string $name): string|null
    {
        return QueryInput::scalar(Yii::$app->getRequest()->getQueryParams(), $name);
    }
}
