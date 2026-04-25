<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\web\View;

/** @var yii\debug\panels\DbPanel $panel */
/** @var yii\debug\models\search\Db $searchModel */
/** @var yii\data\ArrayDataProvider $queryDataProvider */
/** @var bool $hasExplain */
/** @var int $sumDuplicates */
/** @var View $this */

?>

<h1><?= Html::encode($panel->getName()) ?></h1>

<?= $this->render('queries', [
    'panel' => $panel,
    'searchModel' => $searchModel,
    'queryDataProvider' => $queryDataProvider,
    'hasExplain' => $hasExplain,
    'sumDuplicates' => $sumDuplicates,
]) ?>
