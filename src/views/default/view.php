<?php

declare(strict_types=1);

use yii\debug\Panel;
use yii\web\View;
use PHPForge\Debug\View\ViewMessage;

/**
 * @var Panel $activePanel Active panel for the current request view.
 * @var View $this View component instance.
 */
$this->title = ViewMessage::TITLE->value;

?>
<?= $activePanel->getDetail();
