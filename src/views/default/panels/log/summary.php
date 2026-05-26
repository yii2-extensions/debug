<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use yii\debug\html\defaults\{ToolbarBlock, ToolbarLabel};
use yii\debug\panels\LogPanel;
use yii\log\{Logger, Target};

/**
 * @var array{messages?: array<int, array<int, mixed>>} $data Log panel data with captured messages.
 * @var LogPanel $panel Panel providing the toolbar summary data.
 */
$messages = $data['messages'] ?? [];

$errorCount = count(Target::filterMessages($messages, Logger::LEVEL_ERROR));
$warningCount = count(Target::filterMessages($messages, Logger::LEVEL_WARNING));

$allTitle = Yii::$app->i18n->format(
    'Logged {n,plural,=1{1 message} other{# messages}}',
    ['n' => count($messages)],
    'en-US',
);
$errorsTitle = $errorCount > 0
    ? Yii::$app->i18n->format('{n,plural,=1{1 error} other{# errors}}', ['n' => $errorCount], 'en-US')
    : '';
$warningsTitle = $warningCount > 0
    ? Yii::$app->i18n->format('{n,plural,=1{1 warning} other{# warnings}}', ['n' => $warningCount], 'en-US')
    : '';

$titles = array_filter([$allTitle, $errorsTitle, $warningsTitle], static fn(string $title): bool => $title !== '');
$anchors = [
    A::tag()
        ->href($panel->getUrl())
        ->title(implode(",\u{00A0}", $titles))
        ->content('Log ')
        ->html(
            Span::tag()
                ->addDefaultProvider(ToolbarLabel::class)
                ->content((string) count($messages)),
        ),
];

if ($errorCount > 0) {
    $anchors[] = A::tag()
        ->href($panel->getUrl(['Log[level]' => Logger::LEVEL_ERROR]))
        ->html(
            Span::tag()
                ->addDefaultProvider(ToolbarLabel::class)
                ->class('yii-debug-toolbar-label-important')
                ->content((string) $errorCount),
        )
        ->title($errorsTitle);
}

if ($warningCount > 0) {
    $anchors[] = A::tag()
        ->href($panel->getUrl(['Log[level]' => Logger::LEVEL_WARNING]))
        ->html(
            Span::tag()
                ->addDefaultProvider(ToolbarLabel::class)
                ->class('yii-debug-toolbar-label-warning')
                ->content((string) $warningCount),
        )
        ->title($warningsTitle);
}
?>
<?= Div::tag()
    ->addDefaultProvider(ToolbarBlock::class)
    ->html(...$anchors);
