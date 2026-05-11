<?php

declare(strict_types=1);

namespace yii\debug\panels\user;

use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Form\Button;
use UIAwesome\Html\Heading\H2;
use UIAwesome\Html\List\{Dd, Dl, Dt};
use UIAwesome\Html\Phrasing\Span;
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Sectioning\{Article, Section};

/**
 * Renders the user-identity card (hero header + per-section attribute lists) on top of `ui-awesome/html` builders.
 *
 * Stateless static helpers; the public entry point takes a typed {@see UserIdentityView} and returns a ready-to-echo
 * HTML string. Per-attribute branches ('plain' / 'security' reveal button / 'timestamp' relative+absolute) live in
 * private helpers so the public surface stays focused.
 *
 * Usage example:
 * ```php
 * echo \yii\debug\panels\user\UserIdentityRenderer::render($view);
 * ```
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class UserIdentityRenderer
{
    /**
     * Renders the full user-identity card (`<section class="yii-debug-user">` with the hero header + every non-empty
     * section).
     */
    public static function render(UserIdentityView $view): string
    {
        $children = [self::renderHero($view->hero)];

        foreach ($view->sections as $section) {
            $children[] = self::renderSection($section);
        }

        return Section::tag()
            ->class('yii-debug-user')
            ->html(...$children)->render();
    }

    /**
     * Renders one attribute row (`<div class="yii-debug-user-row">` with the `<dt>` label and the kind-specific
     * `<dd>` value).
     */
    private static function renderAttribute(UserAttribute $attribute): Div
    {
        return Div::tag()
            ->class('yii-debug-user-row')
            ->html(
                Dt::tag()->content($attribute->label),
                Dd::tag()->html(self::renderValue($attribute)),
            );
    }

    /**
     * Renders the hero card header (avatar monogram + name + email + status pill + id pill).
     */
    private static function renderHero(UserIdentityHero $hero): Header
    {
        $tags = [];

        if ($hero->statusLabel !== '') {
            $tags[] = Span::tag()
                ->class('yii-debug-user-status yii-debug-user-status-' . $hero->statusVariant)
                ->content($hero->statusLabel);
        }

        if ($hero->idValue !== '') {
            $tags[] = Span::tag()
                ->class('yii-debug-user-pill')
                ->content("ID #{$hero->idValue}");
        }

        $metaChildren = [
            H2::tag()
                ->class('yii-debug-user-name')
                ->content($hero->username),
        ];

        if ($hero->email !== '') {
            $metaChildren[] = P::tag()
                ->class('yii-debug-user-handle')
                ->content($hero->email);
        }

        $metaChildren[] = Div::tag()
            ->class('yii-debug-user-tags')
            ->html(...$tags);

        return Header::tag()
            ->class('yii-debug-user-card')
            ->html(
                Span::tag()
                    ->addAttribute('aria-hidden', 'true')
                    ->class('yii-debug-user-avatar')
                    ->content($hero->monogram),
                Div::tag()
                    ->class('yii-debug-user-meta')
                    ->html(...$metaChildren),
            );
    }

    /**
     * Renders one section (`<article>` with header chip + `<dl>` of attribute rows).
     */
    private static function renderSection(UserIdentitySection $section): Article
    {
        $rows = [];

        foreach ($section->attributes as $attribute) {
            $rows[] = self::renderAttribute($attribute);
        }

        return Article::tag()
            ->class('yii-debug-user-section')
            ->html(
                Header::tag()
                    ->html(
                        Span::tag()
                            ->addAttribute('aria-hidden', 'true')
                            ->class('yii-debug-user-section-icon')
                            ->html($section->icon),
                        Span::tag()
                            ->content($section->label),
                    ),
                Dl::tag()->html(...$rows),
            );
    }

    /**
     * Renders the right-hand `<dd>` content for one attribute, branching on `$attribute->kind`.
     */
    private static function renderValue(UserAttribute $attribute): Span|Button
    {
        if ($attribute->kind === UserAttribute::KIND_EMPTY) {
            return Span::tag()
                ->class('yii-debug-user-empty')
                    ->content('—');
        }

        if ($attribute->kind === UserAttribute::KIND_SECURITY) {
            return Button::tag()
                ->addAriaAttribute('label', 'Reveal ' . $attribute->label)
                ->addDataAttribute('yii-debug-reveal', true)
                ->class('yii-debug-user-reveal')
                ->html(
                    Span::tag()->class('yii-debug-user-mask')->content('••••••••••••'),
                    Span::tag()->class('yii-debug-user-real')->content($attribute->displayValue),
                    Span::tag()->class('yii-debug-user-reveal-cta')->addAttribute('aria-hidden', 'true'),
                )
                ->type('button');
        }

        if ($attribute->kind === UserAttribute::KIND_TIMESTAMP) {
            return Span::tag()
                ->class('yii-debug-user-time')
                ->title($attribute->displayValue)
                ->html(
                    Span::tag()->class('yii-debug-user-time-rel')->content($attribute->timestampRel),
                    Span::tag()->class('yii-debug-user-time-abs')->content($attribute->timestampAbs),
                );
        }

        return Span::tag()
            ->class('yii-debug-user-value')
            ->content($attribute->displayValue);
    }
}
