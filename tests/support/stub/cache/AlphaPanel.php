<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\cache;

use PHPForge\Debug\{Panel, PanelView};

/**
 * Minimal portable panel used to pin the order extension entries are listed in.
 */
final class AlphaPanel extends Panel
{
    protected const string ICON = 'db';
    protected const string ID = 'alpha';
    protected const string TITLE = 'Alpha';

    public function present(array $data): PanelView
    {
        return PanelView::create()->toolbar('Alpha', 1);
    }
}
