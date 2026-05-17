<?php

declare(strict_types=1);

use UIAwesome\Html\Helper\Encode;
use yii\data\ArrayDataProvider;
use yii\debug\models\search\DbSearch;
use yii\debug\panels\DbPanel;
use yii\web\View;

/**
 * @var ArrayDataProvider $queryDataProvider
 * @var bool $hasExplain
 * @var DbPanel $panel
 * @var DbSearch $searchModel
 * @var int $sumDuplicates
 * @var View $this
 */
?>

<h1 class="yii-debug-sr-only"><?= Encode::content($panel->getName()) ?></h1>

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
