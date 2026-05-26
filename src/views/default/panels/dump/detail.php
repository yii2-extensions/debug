<?php

declare(strict_types=1);

use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;
use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;
use yii\debug\models\search\LogSearch;
use yii\debug\panels\dump\{DumpCardRenderer, DumpRowNormalizer};
use yii\debug\panels\DumpPanel;
use yii\debug\widgets\FilterBanner;
use yii\grid\GridView;

/**
 * @var ArrayDataProvider $dataProvider Data provider for the GridView widget.
 * @var DumpPanel $panel Panel providing the detail content.
 * @var LogSearch $searchModel Search model for filtering the dump grid.
 */
?>
<?= H1::tag()
    ->class('yii-debug-sr-only')
    ->content('Dump') ?>
<?= Header::tag()
    ->class('yii-debug-grid-summary')
    ->html(
        Span::tag()
            ->html(
                Strong::tag()->content((string) $dataProvider->getTotalCount()),
                ' dumps captured',
            ),
        GridViewConfig::pageSizeSelectorHtml(),
    ) ?>
<?= FilterBanner::widget(['searchModel' => $searchModel]) ?>
<?= GridView::widget(
        [
            ...GridViewConfig::defaults(),
            'dataProvider' => $dataProvider,
            'id' => 'dump-panel-detailed-grid',
            'filterModel' => $searchModel,
            'columns' => [
                'category',
                [
                    'attribute' => 'message',
                    'value' => static fn(mixed $data, mixed $key, int $index): string => DumpCardRenderer::renderMessageCell(
                        DumpRowNormalizer::from($data),
                        $panel,
                        $index,
                    ),
                    'format' => 'raw',
                    'options' => ['width' => '80%'],
                ],
            ],
        ],
    );
