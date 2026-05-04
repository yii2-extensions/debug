<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\widgets\ListView;

/** @var yii\debug\panels\MailPanel $panel */
/** @var yii\debug\models\search\Mail $searchModel */
/** @var yii\data\ArrayDataProvider $dataProvider */

$totalCount = (int) $dataProvider->getTotalCount();
$hasMessages = $totalCount > 0;
?>

<h1 class="yii-debug-sr-only">Email messages</h1>

<header class="yii-debug-mail-header">
    <div class="yii-debug-mail-header-stat">
        <span class="yii-debug-mail-header-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6">
                <path d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/>
            </svg>
        </span>
        <strong><?= $totalCount ?></strong>
        <span><?= $totalCount === 1 ? 'message' : 'messages' ?> captured</span>
    </div>
    <?php if ($hasMessages): ?>
        <?= Html::button('Filter', [
            'class' => 'yii-debug-btn yii-debug-btn-ghost yii-debug-mail-filter-toggle',
            'type' => 'button',
            'data-yii-debug-toggle' => 'collapse',
            'data-target' => '#email-form',
            'aria-expanded' => 'false',
            'aria-controls' => 'email-form',
        ]) ?>
    <?php endif; ?>
</header>

<?php if ($hasMessages): ?>
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
            <?= Html::submitButton('Apply filters', ['class' => 'yii-debug-btn yii-debug-btn-primary']) ?>
        </div>
        <?php ActiveForm::end(); ?>
    </div>
<?php endif; ?>

<?php if (!$hasMessages): ?>
    <div class="yii-debug-empty-state">
        <h2>No emails sent in this request</h2>
        <p>This request did not dispatch any messages through the Yii mailer, so the inbox is empty.</p>
        <p>The mail panel listens for <code>BaseMailer::EVENT_AFTER_SEND</code>; only requests that actually call <code>$mailer-&gt;send()</code> populate this view. After a Post-Redirect-Get flow, the mail typically lives in the previous (POST) request — open it from the history sidebar.</p>
    </div>
<?php else: ?>
    <?= ListView::widget([
        'dataProvider' => $dataProvider,
        'itemView' => '_item',
        'options' => ['tag' => 'ol', 'class' => 'yii-debug-mail-list'],
        'itemOptions' => ['tag' => 'li', 'class' => 'yii-debug-mail-list-item'],
        'layout' => "{items}\n<div class=\"yii-debug-mail-pager\">{pager}</div>",
    ]) ?>
<?php endif; ?>
