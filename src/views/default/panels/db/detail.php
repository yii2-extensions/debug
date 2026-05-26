<?php

declare(strict_types=1);

use UIAwesome\Html\Heading\H1;
use yii\data\ArrayDataProvider;
use yii\debug\models\search\DbSearch;
use yii\debug\panels\DbPanel;
use yii\web\View;

/**
 * @var bool $hasExplain Whether the database driver supports EXPLAIN.
 * @var DbPanel $panel Panel providing the detail content.
 * @var ArrayDataProvider $queryDataProvider Data provider for the query GridView widget.
 * @var DbSearch $searchModel Search model for filtering the database query grid.
 * @var int $sumDuplicates Number of duplicated queries.
 * @var View $this View component instance.
 */
?>
<?= H1::tag()->class('yii-debug-sr-only')->content($panel->getName()) ?>
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
