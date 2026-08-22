<?php

declare(strict_types=1);

use PHPForge\Debug\Helper\Coerce;
use yii\debug\Module;
use UIAwesome\Html\Flow\P;
use UIAwesome\Html\Form\Button;
use UIAwesome\Html\Form\Values\ButtonType;
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Phrasing\{Code, Span, Strong};
use UIAwesome\Html\Root\Header;
use yii\data\ArrayDataProvider;
use yii\debug\GridViewConfig;
use PHPForge\Debug\Helper\EmptyState;
use yii\debug\models\search\MailSearch;
use PHPForge\Debug\Panel\Mail\MailMessage;
use yii\debug\panels\MailPanel;
use yii\debug\widgets\FilterBanner;
use yii\widgets\{ActiveForm, ListView};

/**
 * @var ArrayDataProvider $dataProvider Data provider for the GridView widget.
 * @var MailPanel $panel Panel providing the detail content.
 * @var MailSearch $searchModel Search model for filtering the mail grid.
 */
$capturedCount = count($panel->getMessages());
$visibleCount = $dataProvider->getTotalCount();

$hasCapturedMessages = $capturedCount > 0;
$hasVisibleMessages = $visibleCount > 0;

/** @var list<MailMessage> $visibleMessages */
$visibleMessages = $dataProvider->allModels;

$failedCount = MailMessage::failedCount($visibleMessages);

$summaryItems = [
    Span::tag()
        ->html(
            Strong::tag()->content((string) $visibleCount),
            ' of ',
            Strong::tag()->content((string) $capturedCount),
            $capturedCount === 1 ? ' message' : ' messages',
        ),
];

if ($failedCount > 0) {
    $summaryItems[] = Span::tag()
        ->class('yii-debug-grid-summary-sep')
        ->content('·');
    $summaryItems[] = Span::tag()
        ->class('yii-debug-grid-summary-stat-danger')
        ->html(
            Strong::tag()->content((string) $failedCount),
            ' failed',
        );
}

if ($hasCapturedMessages) {
    $summaryItems[] = Button::tag()
        ->addAriaAttribute('controls', 'email-form')
        ->addAriaAttribute('expanded', 'false')
        ->addAttribute('data-target', '#email-form')
        ->addAttribute('data-yii-debug-toggle', 'collapse')
        ->class('yii-debug-btn yii-debug-btn-ghost yii-debug-mail-filter-toggle')
        ->content('Filter')
        ->type(ButtonType::BUTTON);
}

if ($hasVisibleMessages) {
    $summaryItems[] = GridViewConfig::pageSizeSelectorHtml();
}
?>
<?= H1::tag()
    ->class('yii-debug-sr-only')
    ->content('Email messages') ?>
<?= Header::tag()
    ->class('yii-debug-grid-summary')
    ->html(...$summaryItems) ?>

<?php if ($hasCapturedMessages): ?>
    <div id="email-form" class="yii-debug-collapsible">
        <?php $form = ActiveForm::begin(
            [
                'action' => Module::route('view', [
                    'tag' => Coerce::string(Yii::$app->request->get('tag')),
                    'panel' => 'mail',
                ]),
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
                'replyTo',
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

<?php if (!$hasCapturedMessages): ?>
    <?= EmptyState::card(
        'No emails sent in this request',
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
<?php elseif (!$hasVisibleMessages): ?>
    <?= FilterBanner::widget(['searchModel' => $searchModel]) ?>
    <?= EmptyState::card(
        'No emails match the active filters',
        P::tag()
            ->content(
                'This request captured email messages, but none match the filters currently applied to the inbox.',
            ),
        P::tag()
            ->content('Remove individual filters or use Clear all above to restore every captured message.'),
    ) ?>
<?php else: ?>
    <?= FilterBanner::widget(['searchModel' => $searchModel]) ?>
    <?= ListView::widget(
        [
            'dataProvider' => $dataProvider,
            'layout' => "{items}\n<div class=\"yii-debug-mail-pager\">{pager}</div>",
            'pager' => GridViewConfig::defaults()['pager'],
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
