<?php

declare(strict_types=1);

use yii\debug\GridViewConfig;
use yii\debug\UserswitchAsset;
use yii\debug\widgets\FilterBanner;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var \yii\web\View $this */
/** @var yii\debug\panels\UserPanel $panel */

UserswitchAsset::register($this);
?>
    <h2>Switch user</h2>
    <div class="yii-debug-grid-2">
        <div>
            <?php $formSet = ActiveForm::begin([
                'action' => \yii\helpers\Url::to(['user/set-identity']),
                'enableClientScript' => false,
                'options' => [
                    'id' => 'debug-userswitch__set-identity',
                    'style' => $panel->canSearchUsers() ? 'display:none' : '',
                    'class' => 'yii-debug-stack',
                ],
            ]);
echo $formSet->field(
    $panel->userSwitch,
    'user[id]',
    ['options' => ['class' => 'yii-debug-field']],
)
    ->textInput(['id' => 'user_id', 'name' => 'user_id', 'class' => 'yii-debug-input'])
    ->label('Switch User', ['class' => 'yii-debug-label']);
echo Html::submitButton('Switch', ['class' => 'yii-debug-btn yii-debug-btn-primary']);
ActiveForm::end();
?>
        </div>
        <div>
            <?php
if (!$panel->userSwitch->isMainUser()) {
    $formReset = ActiveForm::begin([
        'action' => \yii\helpers\Url::to(['user/reset-identity']),
        'enableClientScript' => false,
        'options' => [
            'id' => 'debug-userswitch__reset-identity',
        ],
    ]);
    echo Html::submitButton('Reset to <span class="yii-debug-toolbar-label yii-debug-toolbar-label-info">'
        . $panel->userSwitch->getMainUser()->getId()
        . '</span>', [
            'class' => 'yii-debug-btn yii-debug-btn-ghost',
            'id' => 'debug-userswitch__reset-identity-button',
        ]);
    ActiveForm::end();
}
?>
        </div>
    </div>

<?php
if ($panel->canSearchUsers()) {
    $usersFilterModel = $panel->getUsersFilterModel();
    echo Html::beginTag('div', ['id' => 'debug-userswitch__filter']);
    echo FilterBanner::widget(['searchModel' => $usersFilterModel]);
    echo GridView::widget(array_merge(GridViewConfig::defaults(), [
        'dataProvider' => $panel->getUserDataProvider(),
        'filterModel' => $usersFilterModel,
        'tableOptions' => [
            'class' => 'yii-debug-table yii-debug-table-pointer',
        ],
        'columns' => $panel->filterColumns,
    ]));
    echo Html::endTag('div');
}
?>
