<?php

declare(strict_types=1);

use yii\debug\models\router\{ActionRoutes, CurrentRoute, RouterRules};
use yii\debug\panels\router\RouterRenderer;
use yii\web\View;

/**
 * @var ActionRoutes $actionRoutes
 * @var CurrentRoute $currentRoute
 * @var RouterRules $routerRules
 * @var View $this
 */
?>
<h1 class="yii-debug-sr-only">Router</h1>

<?= RouterRenderer::renderTabs($currentRoute, $routerRules, $actionRoutes);
