<?php

declare(strict_types=1);

namespace yii\debug\widgets;

use Override;
use yii\grid\DataColumn;

/**
 * Applies accessible defaults to debug GridView data columns.
 */
final class DebugDataColumn extends DataColumn
{
    #[Override]
    public function init(): void
    {
        parent::init();

        $this->headerOptions['scope'] ??= 'col';
        $this->filterInputOptions['aria-label'] ??= 'Filter by ' . $this->getHeaderCellLabel();
    }
}
