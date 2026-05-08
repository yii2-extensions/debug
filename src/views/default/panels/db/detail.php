<?php

declare(strict_types=1);

use yii\data\ArrayDataProvider;
use yii\debug\models\search\Db;
use yii\debug\panels\DbPanel;
use yii\helpers\Html;
use yii\web\View;

/**
 * @var ArrayDataProvider $queryDataProvider
 * @var bool $hasExplain
 * @var DbPanel $panel
 * @var Db $searchModel
 * @var int $sumDuplicates
 * @var View $this
 */
?>

<h1 class="yii-debug-sr-only"><?= Html::encode($panel->getName()) ?></h1>

<?= $this->render(
    'queries',
    [
        'panel' => $panel,
        'searchModel' => $searchModel,
        'queryDataProvider' => $queryDataProvider,
        'hasExplain' => $hasExplain,
        'sumDuplicates' => $sumDuplicates,
    ],
);
