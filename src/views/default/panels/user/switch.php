<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Form\Button;
use UIAwesome\Html\Form\Values\ButtonType;
use UIAwesome\Html\Heading\H3;
use UIAwesome\Html\Phrasing\Span;
use yii\debug\{GridViewConfig, UserswitchAsset};
use yii\debug\panels\UserPanel;
use yii\debug\widgets\FilterBanner;
use yii\grid\GridView;
use yii\helpers\Url;
use yii\web\View;
use yii\widgets\ActiveForm;

/**
 * @var UserPanel $panel User panel providing user-switch data.
 * @var View $this View component instance.
 */
UserswitchAsset::register($this);

$userSwitch = $panel->userSwitch;
?>
<?= H3::tag()
    ->content('Switch user') ?>
<div class="yii-debug-grid-2">
    <?php if ($userSwitch !== null): ?>
    <div>
        <?php $formSet = ActiveForm::begin(
            [
                'action' => Url::to(['user/set-identity']),
                'enableClientScript' => false,
                'options' => [
                    'id' => 'debug-userswitch__set-identity',
                    'style' => $panel->canSearchUsers() ? 'display:none' : '',
                    'class' => 'yii-debug-stack',
                ],
            ],
        ); ?>
        <?= $formSet->field(
            $userSwitch,
            'user[id]',
            ['options' => ['class' => 'yii-debug-field']],
        )
        ->textInput(
            [
                'class' => 'yii-debug-input',
                'id' => 'user_id',
                'name' => 'user_id',
            ],
        )
        ->label('Switch User', ['class' => 'yii-debug-label']) ?>
        <?= Button::tag()
            ->type(ButtonType::SUBMIT)
            ->class('yii-debug-btn yii-debug-btn-primary')
            ->content('Switch') ?>
        <?php ActiveForm::end() ?>
    </div>
    <div>
        <?php if (!$userSwitch->isMainUser()): ?>
            <?php ActiveForm::begin(
                [
                    'action' => Url::to(['user/reset-identity']),
                    'enableClientScript' => false,
                    'options' => ['id' => 'debug-userswitch__reset-identity'],
                ],
            ) ?>
            <?= Button::tag()
                ->class('yii-debug-btn yii-debug-btn-ghost')
                ->html(
                    'Reset to ',
                    Span::tag()
                        ->class('yii-debug-toolbar-label yii-debug-toolbar-label-info')
                        ->content((string) $userSwitch->getMainUser()->getId()),
                )
                ->id('debug-userswitch__reset-identity-button')
                ->type(ButtonType::SUBMIT) ?>
            <?php ActiveForm::end() ?>
        <?php endif ?>
    </div>
    <?php endif ?>
</div>

<?php if ($panel->canSearchUsers()): ?>
    <?php $usersFilterModel = $panel->getUsersFilterModel(); ?>
    <?= Div::tag()
        ->html(
            FilterBanner::widget(['searchModel' => $usersFilterModel]),
            GridView::widget(
                [
                    ...GridViewConfig::defaults(),
                    'dataProvider' => $panel->getUserDataProvider(),
                    'filterModel' => $usersFilterModel,
                    'tableOptions' => ['class' => 'yii-debug-table yii-debug-table-pointer yii-debug-table-userswitch'],
                    'columns' => $panel->filterColumns,
                    'layout' => "<div class=\"yii-debug-table-wrap\">{items}</div>\n"
                        . "<div class=\"yii-debug-grid-footer\">{summary}\n{pager}\n</div>",
                ],
            ),
        )
        ->id('debug-userswitch__filter') ?>
<?php endif;
