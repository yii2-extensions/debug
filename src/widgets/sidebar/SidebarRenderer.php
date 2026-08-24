<?php

declare(strict_types=1);

namespace yii\debug\widgets\sidebar;

use PHPForge\Debug\View\Sidebar\SidebarRenderer as CoreSidebarRenderer;

/**
 * Backward-compatible Yii wrapper around the framework-neutral sidebar renderer.
 */
final class SidebarRenderer
{
    /**
     * Renders the full sidebar from the existing Yii route-array view-model.
     */
    public static function render(SidebarView $view): string
    {
        return CoreSidebarRenderer::render($view->toCore());
    }
}
