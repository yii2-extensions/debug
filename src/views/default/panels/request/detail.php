<?php

declare(strict_types=1);

use yii\debug\panels\request\{RequestSectionRenderer, RequestView};

/**
 * @var RequestView $view
 */
?>
<h1 class="yii-debug-sr-only">Request</h1>

<?= RequestSectionRenderer::renderHero($view->hero) ?>
<?= RequestSectionRenderer::renderTabs($view->tabs);
