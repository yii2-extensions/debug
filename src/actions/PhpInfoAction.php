<?php

declare(strict_types=1);

namespace yii\debug\actions;

use Yii;
use yii\debug\widgets\shell\ShellContext;
use yii\debug\widgets\sidebar\SidebarDataNormalizer;

use function array_key_first;

/**
 * Renders the full `phpinfo()` output inside the shared debugger shell.
 *
 * The page belongs to no capture, so it stays outside the panel registry and the navigation highlights nothing; the
 * sidebar is still present, keeping the reader one click from the panels.
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
        $manifest = $this->getManifest();

        $tag = array_key_first($manifest);

        if ($tag !== null) {
            $this->loadData($tag);
        }

        Yii::$app->getView()->params['debugShell'] = $this->createShellContext(
            ShellContext::MODE_VIEW,
            $manifest,
            $tag,
            $tag !== null ? $manifest[$tag] : null,
            SidebarDataNormalizer::fromStandalone($this->getDebugModule()->panels, $manifest),
        );

        return $this->render('phpinfo');
    }
}
