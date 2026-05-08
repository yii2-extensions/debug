<?php

declare(strict_types=1);

use yii\debug\panels\config\{ConfigCardRenderer, ConfigSummary};
use yii\helpers\Url;

/**
 * @var ConfigSummary $summary
 */
?>
<h1 class="yii-debug-sr-only">Configuration</h1>

<?= ConfigCardRenderer::renderReadoutGrid($summary) ?>
<?= ConfigCardRenderer::renderPhpExtensionsSection($summary->php) ?>
<?= ConfigCardRenderer::renderApplicationDetailsSection($summary->application) ?>
<?= ConfigCardRenderer::renderInstalledExtensionsSection($summary) ?>
<?= ConfigCardRenderer::renderPhpInfoCta(Url::to(['php-info']));
