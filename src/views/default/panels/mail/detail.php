<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\widgets\ListView;

/** @var yii\debug\panels\MailPanel $panel */
/** @var yii\debug\models\search\Mail $searchModel */
/** @var yii\data\ArrayDataProvider $dataProvider */

$listView = new ListView([
    'dataProvider' => $dataProvider,
    'itemView' => '_item',
    'layout' => "{summary}\n{items}\n{pager}\n",
]);
$listView->sorter = ['options' => ['class' => 'yii-debug-mail-sorter']];
?>

<h1>Email messages</h1>

<div class="yii-debug-mini-toolbar">
    <?= Html::button('Form filtering', [
        'class' => 'yii-debug-btn yii-debug-btn-ghost',
        'type' => 'button',
        'data-yii-debug-toggle' => 'collapse',
        'data-target' => '#email-form',
        'aria-expanded' => 'false',
        'aria-controls' => 'email-form',
    ]) ?>
    <?= $listView->renderSorter() ?>
</div>

<div id="email-form" class="yii-debug-collapsible">
    <?php $form = ActiveForm::begin([
        'method' => 'get',
        'action' => ['default/view', 'tag' => Yii::$app->request->get('tag'), 'panel' => 'mail'],
        'enableClientScript' => false,
        'options' => ['class' => 'yii-debug-stack'],
    ]); ?>
    <div class="yii-debug-field-grid">
        <?= $form->field($searchModel, 'from', ['options' => ['class' => 'yii-debug-field']])->textInput(['class' => 'yii-debug-input']) ?>

        <?= $form->field($searchModel, 'to', ['options' => ['class' => 'yii-debug-field']])->textInput(['class' => 'yii-debug-input']) ?>

        <?= $form->field($searchModel, 'reply', ['options' => ['class' => 'yii-debug-field']])->textInput(['class' => 'yii-debug-input']) ?>

        <?= $form->field($searchModel, 'cc', ['options' => ['class' => 'yii-debug-field']])->textInput(['class' => 'yii-debug-input']) ?>

        <?= $form->field($searchModel, 'bcc', ['options' => ['class' => 'yii-debug-field']])->textInput(['class' => 'yii-debug-input']) ?>

        <?= $form->field($searchModel, 'charset', ['options' => ['class' => 'yii-debug-field']])->textInput(['class' => 'yii-debug-input']) ?>

        <?= $form->field($searchModel, 'subject', ['options' => ['class' => 'yii-debug-field']])->textInput(['class' => 'yii-debug-input']) ?>

        <?= $form->field($searchModel, 'body', ['options' => ['class' => 'yii-debug-field']])->textInput(['class' => 'yii-debug-input']) ?>
    </div>

    <div>
        <?= Html::submitButton('Filter', ['class' => 'yii-debug-btn yii-debug-btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>

<?= $listView->run() ?>
