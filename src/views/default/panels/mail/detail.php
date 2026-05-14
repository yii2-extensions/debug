<?php

declare(strict_types=1);

use UIAwesome\Html\Form\Button;
use UIAwesome\Html\Form\Values\ButtonType;
use yii\data\ArrayDataProvider;
use yii\debug\helpers\Icon;
use yii\debug\models\search\MailSearch;
use yii\debug\panels\MailPanel;
use yii\widgets\{ActiveForm, ListView};

/**
 * @var ArrayDataProvider $dataProvider
 * @var MailSearch $searchModel
 * @var MailPanel $panel
 */

$totalCount = $dataProvider->getTotalCount();
$hasMessages = $totalCount > 0;
?>

<h1 class="yii-debug-sr-only">Email messages</h1>

<header class="yii-debug-mail-header">
    <div class="yii-debug-mail-header-stat">
        <span class="yii-debug-mail-header-icon" aria-hidden="true"><?= Icon::render('envelope') ?></span>
        <strong><?= $totalCount ?></strong>
        <span><?= $totalCount === 1 ? 'message' : 'messages' ?> captured</span>
    </div>

    <?php if ($hasMessages): ?>
        <?= Button::tag()
            ->type(ButtonType::BUTTON)
            ->class('yii-debug-btn yii-debug-btn-ghost yii-debug-mail-filter-toggle')
            ->addAttribute('data-yii-debug-toggle', 'collapse')
            ->addAttribute('data-target', '#email-form')
            ->addAriaAttribute('expanded', 'false')
            ->addAriaAttribute('controls', 'email-form')
            ->content('Filter')
            ->render() ?>
    <?php endif; ?>
</header>

<?php if ($hasMessages): ?>
    <div id="email-form" class="yii-debug-collapsible">
        <?php $form = ActiveForm::begin(
            [
                'method' => 'get',
                'action' => ['default/view', 'tag' => Yii::$app->request->get('tag'), 'panel' => 'mail'],
                'enableClientScript' => false,
                'options' => ['class' => 'yii-debug-stack'],
            ],
        ); ?>

        <div class="yii-debug-field-grid">
            <?= $form->field($searchModel, 'from', [
                'options' => ['class' => 'yii-debug-field'],
            ])->textInput(['class' => 'yii-debug-input']) ?>
            <?= $form->field($searchModel, 'to', [
                'options' => ['class' => 'yii-debug-field'],
            ])->textInput(['class' => 'yii-debug-input']) ?>
            <?= $form->field($searchModel, 'reply', [
                'options' => ['class' => 'yii-debug-field'],
            ])->textInput(['class' => 'yii-debug-input']) ?>
            <?= $form->field($searchModel, 'cc', [
                'options' => ['class' => 'yii-debug-field'],
            ])->textInput(['class' => 'yii-debug-input']) ?>
            <?= $form->field($searchModel, 'bcc', [
                'options' => ['class' => 'yii-debug-field'],
            ])->textInput(['class' => 'yii-debug-input']) ?>
            <?= $form->field($searchModel, 'charset', [
                'options' => ['class' => 'yii-debug-field'],
            ])->textInput(['class' => 'yii-debug-input']) ?>
            <?= $form->field($searchModel, 'subject', [
                'options' => ['class' => 'yii-debug-field'],
            ])->textInput(['class' => 'yii-debug-input']) ?>
            <?= $form->field($searchModel, 'body', [
                'options' => ['class' => 'yii-debug-field'],
            ])->textInput(['class' => 'yii-debug-input']) ?>
        </div>

        <div>
            <?= Button::tag()
                ->type(ButtonType::SUBMIT)
                ->class('yii-debug-btn yii-debug-btn-primary')
                ->content('Apply filters')
                ->render() ?>
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
    <?= ListView::widget(
        [
            'dataProvider' => $dataProvider,
            'itemView' => '_item',
            'options' => ['tag' => 'ol', 'class' => 'yii-debug-mail-list'],
            'itemOptions' => ['tag' => 'li', 'class' => 'yii-debug-mail-list-item'],
            'layout' => "{items}\n<div class=\"yii-debug-mail-pager\">{pager}</div>",
        ],
    ) ?>
<?php endif;
