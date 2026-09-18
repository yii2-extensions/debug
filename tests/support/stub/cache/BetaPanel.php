<?php

declare(strict_types=1);

namespace yii\debug\tests\support\stub\cache;

use PHPForge\Debug\{Panel, PanelView};

/**
 * Minimal portable panel used to pin the order extension entries are listed in.
 */
final class BetaPanel extends Panel
{
    protected const string ICON = 'db';
    protected const string ID = 'beta';
    protected const string TITLE = 'Beta';

    public function present(array $data): PanelView
    {
        return PanelView::create()->toolbar('Beta', 1);
    }
}
