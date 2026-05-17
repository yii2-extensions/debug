<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Form\Button;
use UIAwesome\Html\Form\Values\ButtonType;
use UIAwesome\Html\Phrasing\Span;
use yii\debug\{GridViewConfig, UserswitchAsset};
use yii\debug\panels\UserPanel;
use yii\debug\widgets\FilterBanner;
use yii\grid\GridView;
use yii\web\View;
use yii\widgets\ActiveForm;

/**
 * @var UserPanel $panel
 * @var View $this
 */

UserswitchAsset::register($this);

$userSwitch = $panel->userSwitch;
?>
    <h3>Switch user</h3>
    <div class="yii-debug-grid-2">
        <?php if ($userSwitch !== null): ?>
        <div>
            <?php $formSet = ActiveForm::begin(
                [
                    'action' => \yii\helpers\Url::to(['user/set-identity']),
                    'enableClientScript' => false,
                    'options' => [
                        'id' => 'debug-userswitch__set-identity',
                        'style' => $panel->canSearchUsers() ? 'display:none' : '',
                        'class' => 'yii-debug-stack',
                    ],
                ],
            );
            echo $formSet->field(
                $userSwitch,
                'user[id]',
                ['options' => ['class' => 'yii-debug-field']],
            )
            ->textInput(['id' => 'user_id', 'name' => 'user_id', 'class' => 'yii-debug-input'])
            ->label('Switch User', ['class' => 'yii-debug-label']);

            echo Button::tag()
                ->type(ButtonType::SUBMIT)
                ->class('yii-debug-btn yii-debug-btn-primary')
                ->content('Switch')
                ->render();

            ActiveForm::end();
            ?>
        </div>
        <div>
            <?php
            if (!$userSwitch->isMainUser()) {
                $formReset = ActiveForm::begin(
                    [
                        'action' => \yii\helpers\Url::to(['user/reset-identity']),
                        'enableClientScript' => false,
                        'options' => ['id' => 'debug-userswitch__reset-identity'],
                    ],
                );
                echo Button::tag()
                    ->type(ButtonType::SUBMIT)
                    ->class('yii-debug-btn yii-debug-btn-ghost')
                    ->id('debug-userswitch__reset-identity-button')
                    ->html(
                        'Reset to ',
                        Span::tag()
                            ->class('yii-debug-toolbar-label yii-debug-toolbar-label-info')
                            ->content((string) $userSwitch->getMainUser()->getId()),
                    )
                    ->render();

                ActiveForm::end();
            }
            ?>
        </div>
        <?php endif; ?>
    </div>

<?php
if ($panel->canSearchUsers()) {
    $usersFilterModel = $panel->getUsersFilterModel();

    echo Div::tag()->id('debug-userswitch__filter')->begin();
    echo FilterBanner::widget(['searchModel' => $usersFilterModel]);
    echo GridView::widget(
        [
            ...GridViewConfig::defaults(),
            'dataProvider' => $panel->getUserDataProvider(),
            'filterModel' => $usersFilterModel,
            'tableOptions' => ['class' => 'yii-debug-table yii-debug-table-pointer yii-debug-table-userswitch'],
            'columns' => $panel->filterColumns,
            // Wrap only the `<table>` (`{items}`) in a horizontally-scrollable container so wide content can be reached
            // by scrolling; the row-count summary and pager stay outside the wrap and align with the panel width like
            // the other grids.
            'layout' => "<div class=\"yii-debug-table-wrap\">{items}</div>\n"
                . "<div class=\"yii-debug-grid-footer\">{summary}\n{pager}\n</div>",
        ],
    );
    echo Div::end();
}
?>
