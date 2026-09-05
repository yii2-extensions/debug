<?php

declare(strict_types=1);

use PHPForge\Debug\Panel\Request\{RequestRenderer, RequestView};
use PHPForge\Debug\Panel\Request\Routing\RequestRoutingView;
use UIAwesome\Html\Heading\H1;

/** @var RequestView $view Typed request view payload */
/** @var RequestRoutingView $routing Composed request routing diagnostics */
?>
<?= H1::tag()->class('yii-debug-sr-only')->content('Request') ?>
<?= RequestRenderer::render($view, $routing);
