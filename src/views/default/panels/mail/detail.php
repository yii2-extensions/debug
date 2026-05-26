<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Form\Button;
use UIAwesome\Html\Form\Values\ButtonType;
use UIAwesome\Html\Heading\{H1, H2};
use UIAwesome\Html\Phrasing\{Code, Span, Strong};
use UIAwesome\Html\Root\Header;
use yii\data\ArrayDataProvider;
use yii\debug\helpers\Icon;
use yii\debug\models\search\MailSearch;
use yii\debug\panels\MailPanel;
use yii\widgets\{ActiveForm, ListView};

/**
 * @var ArrayDataProvider $dataProvider Data provider for the GridView widget.
 * @var MailPanel $panel Panel providing the detail content.
 * @var MailSearch $searchModel Search model for filtering the mail grid.
 */
$totalCount = $dataProvider->getTotalCount();

$hasMessages = $totalCount > 0;

$headerItems = [
    Div::tag()
        ->class('yii-debug-mail-header-stat')
        ->html(
            Span::tag()
                ->addAriaAttribute('hidden', 'true')
                ->class('yii-debug-mail-header-icon')
                ->html(Icon::render('envelope')),
            Strong::tag()
                ->content((string) $totalCount),
            Span::tag()
                ->content(($totalCount === 1 ? 'message' : 'messages') . ' captured'),
        ),
];

if ($hasMessages) {
    $headerItems[] = Button::tag()
        ->addAriaAttribute('controls', 'email-form')
        ->addAriaAttribute('expanded', 'false')
        ->addAttribute('data-target', '#email-form')
        ->addAttribute('data-yii-debug-toggle', 'collapse')
        ->class('yii-debug-btn yii-debug-btn-ghost yii-debug-mail-filter-toggle')
        ->content('Filter')
        ->type(ButtonType::BUTTON);
}
?>
<?= H1::tag()
    ->class('yii-debug-sr-only')
    ->content('Email messages') ?>
<?= Header::tag()
    ->class('yii-debug-mail-header')
    ->html(...$headerItems) ?>

<?php if ($hasMessages): ?>
    <div id="email-form" class="yii-debug-collapsible">
        <?php $form = ActiveForm::begin(
            [
                'action' => [
                    'default/view',
                    'tag' => Yii::$app->request->get('tag'),
                    'panel' => 'mail',
                ],
                'enableClientScript' => false,
                'method' => 'get',
                'options' => ['class' => 'yii-debug-stack'],
            ],
        ); ?>

        <div class="yii-debug-field-grid">
            <?= $form->field(
                $searchModel,
                'from',
                ['options' => ['class' => 'yii-debug-field']],
            )->textInput(['class' => 'yii-debug-input']) ?>
            <?= $form->field(
                $searchModel,
                'to',
                ['options' => ['class' => 'yii-debug-field']],
            )->textInput(['class' => 'yii-debug-input']) ?>
            <?= $form->field(
                $searchModel,
                'reply',
                ['options' => ['class' => 'yii-debug-field']],
            )->textInput(['class' => 'yii-debug-input']) ?>
            <?= $form->field(
                $searchModel,
                'cc',
                ['options' => ['class' => 'yii-debug-field']],
            )->textInput(['class' => 'yii-debug-input']) ?>
            <?= $form->field(
                $searchModel,
                'bcc',
                ['options' => ['class' => 'yii-debug-field']],
            )->textInput(['class' => 'yii-debug-input']) ?>
            <?= $form->field(
                $searchModel,
                'charset',
                ['options' => ['class' => 'yii-debug-field']],
            )->textInput(['class' => 'yii-debug-input']) ?>
            <?= $form->field(
                $searchModel,
                'subject',
                ['options' => ['class' => 'yii-debug-field']],
            )->textInput(['class' => 'yii-debug-input']) ?>
            <?= $form->field(
                $searchModel,
                'body',
                ['options' => ['class' => 'yii-debug-field']],
            )->textInput(['class' => 'yii-debug-input']) ?>
        </div>

        <div>
            <?= Button::tag()
                ->class('yii-debug-btn yii-debug-btn-primary')
                ->content('Apply filters')
                ->type(ButtonType::SUBMIT) ?>
        </div>

        <?php ActiveForm::end(); ?>
    </div>
<?php endif; ?>

<?php if (!$hasMessages): ?>
    <?= Div::tag()
        ->class('yii-debug-empty-state')
        ->html(
            H2::tag()
                ->content('No emails sent in this request'),
            P::tag()
                ->content('This request did not dispatch any messages through the Yii mailer, so the inbox is empty.'),
            P::tag()
                ->html(
                    'The mail panel listens for ',
                    Code::tag()->content('BaseMailer::EVENT_AFTER_SEND'),
                    '; only requests that actually call ',
                    Code::tag()->content('$mailer->send()'),
                    ' populate this view. After a Post-Redirect-Get flow, the mail typically lives in the previous (POST) '
                    . 'request — open it from the history sidebar.',
                ),
        ) ?>
<?php else: ?>
    <?= ListView::widget(
        [
            'dataProvider' => $dataProvider,
            'layout' => "{items}\n<div class=\"yii-debug-mail-pager\">{pager}</div>",
            'itemOptions' => [
                'class' => 'yii-debug-mail-list-item',
                'tag' => 'li',
            ],
            'itemView' => '_item',
            'options' => [
                'class' => 'yii-debug-mail-list',
                'tag' => 'ol',
            ],
        ],
    ) ?>
<?php endif;
