<?php

declare(strict_types=1);

namespace yii\debug\widgets;

use Override;
use PHPForge\Debug\View\Grid\GridCount;

/**
 * Renders a debugger grid whose footer sentence comes from the shared {@see GridCount} renderer.
 */
final class GridView extends \yii\grid\GridView
{
    /**
     * Renders the row-count sentence for the rows currently on screen.
     *
     * Derives the counters the way {@see \yii\widgets\BaseListView::renderSummary()} does: the paginator's offset opens
     * the range and the number of rows rendered on the page closes it, while disabled pagination reports the whole set
     * as a single page. An empty page reports `0-0`, matching the shared renderer instead of collapsing to an empty
     * string.
     *
     * @return string Rendered row-count sentence.
     */
    #[Override]
    public function renderSummary(): string
    {
        $count = $this->dataProvider->getCount();
        $pagination = $this->dataProvider->getPagination();

        $total = $pagination === false ? $count : $this->dataProvider->getTotalCount();

        if ($count === 0) {
            return GridCount::render(0, 0, $total);
        }

        $begin = $pagination === false ? 1 : $pagination->getOffset() + 1;

        return GridCount::render($begin, $begin + $count - 1, $total);
    }
}
