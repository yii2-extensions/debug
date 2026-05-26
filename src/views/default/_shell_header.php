<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Form\Button;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use UIAwesome\Html\Root\Header;
use yii\debug\helpers\Icon;
use yii\debug\html\defaults\BrandChip;
use yii\helpers\Url;
use yii\web\View;

/**
 * @var string|null $configUrl URL to the Configuration panel for the active (or latest) request, or `null` when the
 * manifest is empty the chip then renders disabled with a hint.
 * @var string $debugTheme Resolved theme key, `'light'` or `'dark'`.
 * @var string|null $peakMemory Optional formatted peak-memory chip (for example, `1.21 MB`); pass `null` to omit the
 * chip index.php does this because there's no active request.
 * @var string $phpVersion Friendly PHP version, for example, `8.5.3`.
 * @var string $themeIconMoon Pre-loaded moon glyph.
 * @var string $themeIconSun Pre-loaded sun glyph from the controller.
 * @var string $yiiVersion Friendly framework version, for example, `22.0.x-dev`.
 * @var View $this View component instance.
 */
$themeChipIcon = $debugTheme === 'dark' ? $themeIconSun : $themeIconMoon;

$configIcon = Icon::render('config');
$yiiChip = A::tag()
    ->addDefaultProvider(BrandChip::class)
    ->class('yii-debug-brand-chip-yii')
    ->href(Url::to(['index']))
    ->html(
        Span::tag()
            ->class('yii-debug-brand-icon')
            ->html(Icon::render('yii')),
        Span::tag()
            ->class('yii-debug-brand-label')
            ->content('Yii'),
        Span::tag()
            ->class('yii-debug-brand-value')
            ->content($yiiVersion)
    );
$phpChip = Div::tag()
    ->addDefaultProvider(BrandChip::class)
    ->class('yii-debug-brand-chip-php')
    ->html(
        Span::tag()
            ->class('yii-debug-brand-icon')
            ->html(Icon::render('php-alt')),
        Span::tag()
            ->class('yii-debug-brand-value')
            ->content($phpVersion)
    );
$memChip = $peakMemory === null
    ? ''
    : Div::tag()
        ->addDefaultProvider(BrandChip::class)
        ->class('yii-debug-brand-chip-mem')
        ->html(
            Span::tag()
                ->class('yii-debug-brand-label')
                ->content('Memory'),
            Span::tag()
                ->class('yii-debug-brand-value')
                ->content($peakMemory)
        );
$configIconSpan = Span::tag()
    ->addAriaAttribute('hidden', 'true')
    ->class('yii-debug-brand-icon')
    ->html($configIcon);
$configLabel = Span::tag()
    ->class('yii-debug-brand-label')
    ->content('Config');
$configChip = $configUrl !== null
    ? A::tag()
        ->addAriaAttribute('label', 'Open the Configuration panel')
        ->addDefaultProvider(BrandChip::class)
        ->class('yii-debug-brand-chip-config')
        ->href($configUrl)
        ->html($configIconSpan, $configLabel)
        ->title('Open the Configuration panel')
    : Span::tag()
        ->addAriaAttribute('disabled', 'true')
        ->addDefaultProvider(BrandChip::class)
        ->class('yii-debug-brand-chip-config is-disabled')
        ->html($configIconSpan, $configLabel)
        ->title('No requests captured yet');
$themeChip = Button::tag()
    ->addAriaAttribute('label', 'Toggle debug panel theme')
    ->addDataAttribute('yii-debug-theme-toggle', true)
    ->addDefaultProvider(BrandChip::class)
    ->class('yii-debug-brand-chip-theme')
    ->dataAttributes(
        [
            'current-theme' => $debugTheme,
            'icon-sun' => $themeIconSun,
            'icon-moon' => $themeIconMoon,
        ]
    )
    ->html(
        Span::tag()
            ->class('yii-debug-brand-icon')
            ->addAriaAttribute('hidden', 'true')
            ->html($themeChipIcon)
    )
    ->title('Toggle debug panel theme')
    ->type('button');
?>
<?= Header::tag()
    ->class('yii-debug-brand-bar')
    ->html(
        $yiiChip,
        $phpChip,
        $memChip,
        $configChip,
        $themeChip,
    );
