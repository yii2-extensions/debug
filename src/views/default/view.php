<?php

declare(strict_types=1);

use yii\debug\Panel;
use yii\web\View;

/**
 * @var Panel $activePanel Active panel for the current request view.
 * @var View $this View component instance.
 */
$this->title = 'Yii Debugger';

?>
<?= $activePanel->getDetail();
