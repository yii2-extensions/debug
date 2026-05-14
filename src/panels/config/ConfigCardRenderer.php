<?php

declare(strict_types=1);

namespace yii\debug\panels\config;

use Locale;
use Stringable;
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Heading\H2;
use UIAwesome\Html\List\{Dd, Dl, Dt};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use UIAwesome\Html\Sectioning\{Article, Section};

use function array_map;
use function class_exists;
use function implode;
use function is_string;

/**
 * Renders the typed sections of the Configuration panel detail view.
 *
 * Stateless static helpers: every method takes the data it needs as arguments and returns the rendered HTML tree.
 * Concentrates the render logic (readout cards, extension pills, package list, php-info CTA) in one testable place,
 * keeping the detail view focused on page-level scaffolding.
 */
final class ConfigCardRenderer
{
    private const array CORNERS = [
        'tl',
        'tr',
        'bl',
        'br',
    ];

    /**
     * Renders the `Application details` description list (charset, current language, source language).
     */
    public static function renderApplicationDetailsSection(ApplicationConfig $app): Section
    {
        return Section::tag()
            ->class('yii-debug-section')
            ->html(
                H2::tag()
                    ->class('yii-debug-section-title')
                    ->html(
                        Span::tag()
                            ->class('yii-debug-section-mark')
                            ->content('//'),
                        ' Application details',
                    ),
                Dl::tag()
                    ->class('yii-debug-dl')
                    ->html(
                        self::renderDlRow('Charset', $app->charset !== '' ? $app->charset : '—'),
                        self::renderDlRow('Current language', self::formatLanguage($app->language)),
                        self::renderDlRow('Source language', self::formatLanguage($app->sourceLanguage)),
                    ),
            );
    }

    /**
     * Renders the Installed extensions section, or returns `null` when the roster is empty so the caller can omit the
     * wrapper entirely.
     */
    public static function renderInstalledExtensionsSection(ConfigSummary $summary): Section|null
    {
        if ($summary->hasExtensions() === false) {
            return null;
        }

        $items = [];

        foreach ($summary->extensions as $name => $version) {
            $items[] = self::renderPackageItem($name, $version);
        }

        return Section::tag()
            ->class('yii-debug-section')
            ->html(
                H2::tag()
                    ->class('yii-debug-section-title')
                    ->html(
                        Span::tag()
                            ->class('yii-debug-section-mark')
                            ->content('>_'),
                        ' Installed extensions ',
                        Span::tag()
                            ->class('yii-debug-section-count')
                            ->content((string) $summary->extensionCount()),
                    ),
                Div::tag()
                    ->class('yii-debug-packages')
                    ->html(...$items),
            );
    }

    /**
     * Renders the labeled on/off pill strip for the bundled PHP extensions (Xdebug, APCu, Memcache, Memcached).
     */
    public static function renderPhpExtensionsSection(PhpConfig $php): Section
    {
        $pills = [
            self::renderExtensionPill('Xdebug', $php->xdebug),
            self::renderExtensionPill('APCu', $php->apcu),
            self::renderExtensionPill('Memcache', $php->memcache),
            self::renderExtensionPill('Memcached', $php->memcached),
        ];

        return Section::tag()
            ->class('yii-debug-section')
            ->html(
                H2::tag()
                    ->class('yii-debug-section-title')
                    ->html(
                        Span::tag()
                            ->class('yii-debug-section-mark')
                            ->content('::'),
                        ' PHP extensions',
                    ),
                Div::tag()
                    ->class('yii-debug-ext-strip')
                    ->html(...$pills),
            );
    }

    /**
     * Renders the bottom call-to-action linking to the standalone phpinfo viewer.
     *
     * The caller resolves the destination URL (typically via `Url::to(['php-info'])`) so the renderer stays free of
     * routing concerns and easy to test in isolation.
     */
    public static function renderPhpInfoCta(string $href): A
    {
        return A::tag()
            ->class('yii-debug-cta')
            ->href($href)
            ->target('_blank')
            ->rel('noopener')
            ->html(
                Span::tag()
                    ->class('yii-debug-cta-prompt')
                    ->addAriaAttribute('hidden', 'true')
                    ->content('→'),
                Span::tag()
                    ->content('View full phpinfo'),
                Span::tag()
                    ->class('yii-debug-cta-external')
                    ->addAriaAttribute('hidden', 'true')
                    ->content('↗'),
            );
    }

    /**
     * Renders the four-card readout grid (`Yii`, `PHP`, `Environment`, `Application`) at the top of the detail view.
     */
    public static function renderReadoutGrid(ConfigSummary $summary): Div
    {
        $app = $summary->application;
        $php = $summary->php;

        $envMeta = $app->debug
            ? Span::tag()
                ->class('yii-debug-readout-chip')
                ->content("debug\u{00A0}on")
            : Span::tag()
                ->class('yii-debug-readout-chip yii-debug-readout-chip-muted')
                ->content("debug\u{00A0}off");

        $applicationMeta = $app->version !== ''
            ? Span::tag()
                ->class('yii-debug-readout-chip yii-debug-readout-chip-muted')
                ->content("v{$app->version}")
            : 'instance';

        return Div::tag()
            ->class('yii-debug-readout')
            ->html(
                self::renderReadoutCard('Yii', $app->yii, 'framework'),
                self::renderReadoutCard('PHP', $php->version, 'runtime'),
                self::renderReadoutCard('Environment', $app->env, $envMeta),
                self::renderReadoutCard('Application', $app->name !== '' ? $app->name : '—', $applicationMeta),
            );
    }

    /**
     * Returns a BCP-47 tag annotated with its English display name when `ext-intl` is available.
     *
     * Falls back to the locale itself when `ext-intl` is missing, or to the em-dash placeholder when the locale is
     * empty.
     */
    private static function formatLanguage(string $locale): string
    {
        if ($locale === '') {
            return '—';
        }

        if (class_exists('Locale', false) === false) {
            return $locale;
        }

        $parts = [];

        foreach ([Locale::getDisplayLanguage($locale, 'en'), Locale::getDisplayRegion($locale, 'en')] as $part) {
            if (is_string($part) && $part !== '') {
                $parts[] = $part;
            }
        }

        return $parts === [] ? $locale : "{$locale} (" . implode(', ', $parts) . ')';
    }

    /**
     * Builds the four decorative corner glyphs that frame every readout card.
     *
     * @return list<Span> Corner spans in `tl`, `tr`, `bl`, `br` order.
     */
    private static function renderCorners(): array
    {
        return array_map(
            static fn(string $corner): Span => Span::tag()
                ->class('yii-debug-readout-corner')
                ->addDataAttribute('corner', $corner)
                ->addAriaAttribute('hidden', 'true'),
            self::CORNERS,
        );
    }

    /**
     * Renders one `<dt>term</dt><dd>value</dd>` row inside the application-details description list.
     */
    private static function renderDlRow(string $term, string $value): Div
    {
        return Div::tag()
            ->class('yii-debug-dl-row')
            ->html(
                Dt::tag()
                    ->content($term),
                Dd::tag()
                    ->content($value),
            );
    }

    /**
     * Renders one extension pill with an on/off state and a label.
     */
    private static function renderExtensionPill(string $name, bool $enabled): Span
    {
        return Span::tag()
            ->class('yii-debug-ext-pill ' . ($enabled ? 'is-on' : 'is-off'))
            ->html(
                Span::tag()
                    ->addAriaAttribute('hidden', 'true')
                    ->class('yii-debug-ext-pill-dot'),
                Span::tag()
                    ->class('yii-debug-ext-pill-label')
                    ->content($name),
                Span::tag()
                    ->class('yii-debug-ext-pill-state')
                    ->content($enabled ? 'on' : 'off'),
            );
    }

    /**
     * Renders one installed-package item with its name and version.
     */
    private static function renderPackageItem(string $name, string $version): Article
    {
        return Article::tag()
            ->class('yii-debug-package')
            ->html(
                Span::tag()
                    ->addAriaAttribute('hidden', 'true')
                    ->class('yii-debug-package-glyph')
                    ->content('◆'),
                Span::tag()
                    ->class('yii-debug-package-name')
                    ->content($name),
                Span::tag()
                    ->class('yii-debug-package-version')
                    ->content("v{$version}"),
            );
    }

    /**
     * Builds one readout card with a label, value, and either a plain-text meta line or a chip.
     */
    private static function renderReadoutCard(string $label, string $value, Span|string $meta): Article
    {
        $metaWrap = Span::tag()
            ->class('yii-debug-readout-meta');

        $metaWrap = $meta instanceof Stringable
            ? $metaWrap->html($meta)
            : $metaWrap->content($meta);

        $children = [
            ...self::renderCorners(),
            Span::tag()
                ->class('yii-debug-readout-label')
                ->content($label),
            Span::tag()
                ->class('yii-debug-readout-value')
                ->content($value),
            $metaWrap,
        ];

        return Article::tag()->class('yii-debug-readout-card')
            ->html(...$children);
    }
}
