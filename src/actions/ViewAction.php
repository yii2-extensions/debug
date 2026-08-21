<?php

declare(strict_types=1);

namespace yii\debug\actions;

use yii\debug\exception\Message;
use yii\web\NotFoundHttpException;

use function array_key_first;

/**
 * Renders the snapshot detail page focused on one captured debug entry and panel.
 */
class ViewAction extends Action
{
    /**
     * Runs the action.
     *
     * Falls back to the most recent tag when `$tag` is omitted and to the module's default panel when `$panel` is
     * omitted or unknown. Panel-reported errors are rendered through Yii's exception view instead of the panel
     * template.
     *
     * @param string|null $tag Tag of the debug entry to render, or `null` to use the most recent one.
     * @param string|null $panel Panel ID to focus, or `null` to use the module's default panel.
     *
     * @throws NotFoundHttpException When no debug entries are available or the resolved tag cannot be loaded.
     *
     * @return string Rendered panel view, or the rendered exception view when the panel reported an error.
     */
    public function run(string|null $tag = null, string|null $panel = null): string
    {
        if ($tag === null) {
            $tag = array_key_first($this->getManifest());

            if ($tag === null) {
                throw new NotFoundHttpException(
                    Message::DEBUG_DATA_EMPTY->getMessage(),
                );
            }
        }

        $this->loadData($tag);

        $module = $this->getDebugModule();

        if ($panel !== null && isset($module->panels[$panel])) {
            $activePanel = $module->panels[$panel];
        } else {
            $activePanel = $this->getPanel($module->defaultPanel);
        }

        $error = $activePanel->getError();

        if ($error !== null) {
            return $this->renderPanelError($error);
        }

        $this->prepareShell($activePanel, $tag);

        return $this->render(
            'view',
            ['activePanel' => $activePanel],
        );
    }
}
