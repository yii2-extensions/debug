<?php

declare (strict_types=1);

use yii\debug\DebugAsset;
use yii\helpers\Html;
use yii\web\View;

/**
 * @var string $content
 * @var View $this
 */
DebugAsset::register($this);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="none"/>
    <?= Html::csrfMetaTags() ?>
    <title><?= Html::encode(Yii::$app->controller->module->htmlTitle()) ?></title>
    <?php $this->head() ?>
</head>
<body>
<?php $this->beginBody() ?>
<?= $content ?>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
