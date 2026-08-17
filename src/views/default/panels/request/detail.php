<?php

declare(strict_types=1);

use UIAwesome\Html\Heading\H1;
use PHPForge\Debug\Panel\Request\{RequestSectionRenderer, RequestView};

/** @var RequestView $view Typed request view payload */
?>
<?= H1::tag()->class('yii-debug-sr-only')->content('Request') ?>
<?= RequestSectionRenderer::renderHero($view->hero) ?>
<?= RequestSectionRenderer::renderTabs($view->tabs);
