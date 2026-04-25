<?php

declare(strict_types=1);

namespace yii\debug;

use function in_array;

/**
 * Shared default configuration for GridView widgets rendered inside the debug panel UI.
 *
 * Centralizes the pager markup, table classes, and row-status helpers so every `GridView::widget(...)` call in the
 * debug views emits consistent, namespaced, framework-agnostic markup.
 *
 * Usage example:
 *
 * ```php
 * echo GridView::widget(
 *     array_merge(
 *         GridViewConfig::defaults(),
 *         [
 *             'dataProvider' => $dataProvider,
 *             'columns' => [...],
 *             'rowOptions' => function ($model) {
 *                 return GridViewConfig::rowClassFor($model['level'] ?? null);
 *             },
 *         ],
 *     ),
 * );
 */
final class GridViewConfig
{
    /**
     * Returns the default GridView options for the debug UI.
     *
     * @return array<string, mixed> Merge-friendly options keyed by `tableOptions`, `options`, `pager`, `layout`,
     * `summary`, and `emptyText`.
     */
    public static function defaults(): array
    {
        return [
            'tableOptions' => ['class' => 'yii-debug-table'],
            'options' => ['class' => 'yii-debug-grid'],
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
}
