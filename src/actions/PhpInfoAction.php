<?php

declare(strict_types=1);

namespace yii\debug\actions;

/**
 * Renders the full `phpinfo()` output in a standalone page (no sidebar).
 *
 * Kept outside the panel registry so the entry never appears on the sidebar nav; the Configuration panel links to it
 * via a CTA that opens in a new tab.
 */
class PhpInfoAction extends Action
{
    /**
     * Runs the action.
     *
     * @return string Rendered phpinfo view.
     */
    public function run(): string
    {
        return $this->render('phpinfo');
    }
}
