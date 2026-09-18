<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\cache;

use PHPForge\Debug\{ColumnStyle, Panel, PanelView};

use function array_is_list;
use function count;
use function is_array;

/**
 * Presents the operations recorded by {@see CacheCollector} for the selected capture.
 */
final class CachePanel extends Panel
{
    protected const string ICON = 'db';
    protected const string ID = 'cache';
    protected const string TITLE = 'Cache';

    public function present(array $data): PanelView
    {
        $recorded = $data['operations'] ?? null;
        $operations = is_array($recorded) && array_is_list($recorded) ? $recorded : [];
        $count = count($operations);

        $view = PanelView::create()
            ->summary($count === 1 ? ' operation' : ' operations', $count)
            ->toolbar('Cache', $count);

        return $operations === []
            ? $view->emptyState('No cache operations', 'The cache was observed, but nothing happened.')
            : $view->table(
                ['Operation', 'Key', 'Result'],
                $operations,
                collapsible: true,
                styles: [1 => ColumnStyle::IDENTIFIER],
            );
    }
}
