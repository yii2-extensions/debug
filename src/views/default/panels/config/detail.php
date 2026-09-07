<?php

declare(strict_types=1);

use PHPForge\Debug\Panel\PanelTitle;
use yii\debug\Module;
use UIAwesome\Html\Heading\H1;
use PHPForge\Debug\Panel\Config\{ConfigCardRenderer, ConfigSummary};
use yii\helpers\Url;

/** @var ConfigSummary $summary Typed configuration summary. */
?>
<?= H1::tag()->class('yii-debug-sr-only')->content(PanelTitle::CONFIGURATION) ?>
<?= ConfigCardRenderer::renderReadoutGrid($summary) ?>
<?= ConfigCardRenderer::renderPhpExtensionsSection($summary->php) ?>
<?= ConfigCardRenderer::renderApplicationDetailsSection($summary->application) ?>
<?= ConfigCardRenderer::renderInstalledExtensionsSection($summary) ?>
<?= ConfigCardRenderer::renderPhpInfoCta(Url::to(Module::route('php-info')));
