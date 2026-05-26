<?php

declare(strict_types=1);

use UIAwesome\Html\Heading\H1;
use yii\debug\panels\config\{ConfigCardRenderer, ConfigSummary};
use yii\helpers\Url;

/** @var ConfigSummary $summary Typed configuration summary. */
?>
<?= H1::tag()->class('yii-debug-sr-only')->content('Configuration') ?>
<?= ConfigCardRenderer::renderReadoutGrid($summary) ?>
<?= ConfigCardRenderer::renderPhpExtensionsSection($summary->php) ?>
<?= ConfigCardRenderer::renderApplicationDetailsSection($summary->application) ?>
<?= ConfigCardRenderer::renderInstalledExtensionsSection($summary) ?>
<?= ConfigCardRenderer::renderPhpInfoCta(Url::to(['php-info']));
