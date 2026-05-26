<?php

declare(strict_types=1);

use UIAwesome\Html\Heading\H1;
use yii\debug\models\router\{ActionRoutes, CurrentRoute, RouterRules};
use yii\debug\panels\router\RouterRenderer;
use yii\web\View;

/**
 * @var ActionRoutes $actionRoutes Resolved action routes.
 * @var CurrentRoute $currentRoute Current request route.
 * @var RouterRules $routerRules Configured URL rules.
 * @var View $this View component instance.
 */
?>
<?= H1::tag()->class('yii-debug-sr-only')->content('Router') ?>
<?= RouterRenderer::renderTabs($currentRoute, $routerRules, $actionRoutes);
