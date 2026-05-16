<?php

declare(strict_types=1);

namespace yii\debug\widgets\sidebar;

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Form\Button;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Sectioning\{Aside, Nav, Section};
use yii\debug\helpers\Icon;
use yii\helpers\Url;

/**
 * Renders the debugger sidebar partial.
 *
 * Stateless static helpers: the public entry point takes a typed {@see SidebarView} and returns ready-to-echo HTML.
 *
 * The snapshot card (top section) and the panel-list nav (bottom section) are built by private helpers, so the
 * `_sidebar.php` partial collapses to a single call.
 */
final class SidebarRenderer
{
    private const string ICON_BTN_CLASS = 'yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon';

    /**
     * Renders the full sidebar (`<aside>` with the snapshot card + panel nav).
     */
    public static function render(SidebarView $view): string
    {
        $children = [];

        if ($view->snapshot !== null) {
            $children[] = self::renderSnapshotSection($view->snapshot);
        }

        $children[] = self::renderPanelNav($view->navItems);

        return Aside::tag()
            ->class('yii-debug-sidebar')
            ->html(...$children)
            ->render();
    }

    /**
     * Renders the snapshot card body (method/url line + meta strip + navigator row).
     */
    private static function renderHistoryCard(SidebarSnapshot $snapshot): Div
    {
        return Div::tag()
            ->class('yii-debug-history-card')
            ->title(($snapshot->method !== '' ? $snapshot->method . ' ' : '') . $snapshot->fullUrl)
            ->html(
                Div::tag()
                    ->class('yii-debug-snapshot-line')
                    ->html(
                        Span::tag()
                            ->class('yii-debug-snapshot-method')
                            ->addDataAttribute('snapshot-field', 'method')
                            ->content($snapshot->method),
                        Span::tag()
                            ->class('yii-debug-snapshot-url')
                            ->addDataAttribute('snapshot-field', 'url')
                            ->title($snapshot->fullUrl)
                            ->content($snapshot->path),
                    ),
                self::renderMetaStrip($snapshot),
                self::renderNavRow($snapshot),
            );
    }

    /**
     * Renders the snapshot card meta strip (status pill + time chip + AJAX tag).
     */
    private static function renderMetaStrip(SidebarSnapshot $snapshot): Div
    {
        $time = Span::tag()
            ->class('yii-debug-snapshot-time')
            ->addDataAttribute('snapshot-field', 'time')
            ->content($snapshot->time);

        if ($snapshot->time === '') {
            $time = $time->addAttribute('hidden', true);
        }

        $ajax = Span::tag()
            ->class('yii-debug-snapshot-tag')
            ->addDataAttribute('snapshot-field', 'ajax')
            ->content('AJAX');

        if ($snapshot->isAjax === false) {
            $ajax = $ajax->addAttribute('hidden', true);
        }

        return Div::tag()
            ->class('yii-debug-snapshot-meta')
            ->html(
                Span::tag()
                    ->class('yii-debug-snapshot-status yii-debug-snapshot-status-' . $snapshot->statusVariant)
                    ->addDataAttribute('snapshot-field', 'status')
                    ->content($snapshot->statusCode > 0 ? (string) $snapshot->statusCode : '–'),
                $time,
                $ajax,
            );
    }

    /**
     * Renders one navigator button (either an anchor link or a cursor `<button>` depending on cursor mode).
     *
     * @param array<int|string, string> $url
     */
    private static function renderNavButton(
        bool $isCursor,
        string $cursorTarget,
        bool $isDisabled,
        array $url,
        string $title,
        string $ariaLabel,
        string $icon,
    ): A|Button {
        $class = self::ICON_BTN_CLASS . ($isDisabled ? ' is-disabled' : '');

        if ($isCursor) {
            return Button::tag()
                ->type('button')
                ->class($class)
                ->addDataAttribute('yii-debug-cursor', $cursorTarget)
                ->title($title)
                ->addAriaAttribute('label', $ariaLabel)
                ->html($icon);
        }

        return A::tag()
            ->class($class)
            ->href($url !== [] ? Url::to($url) : '')
            ->title($title)
            ->addAriaAttribute('label', $ariaLabel)
            ->html($icon);
    }

    /**
     * Renders the navigator row (First | Prev | Next | Latest), branching between cursor-mode buttons and
     * navigation-mode anchor links.
     */
    private static function renderNavRow(SidebarSnapshot $snapshot): Div
    {
        $iconFirst = Icon::render('chevrons-up');
        $iconPrev = Icon::render('chevron-up');
        $iconNext = Icon::render('chevron-down');
        $iconLatest = Icon::render('chevrons-down');

        return Div::tag()
            ->class('yii-debug-request-nav-row')
            ->addAttribute('role', 'group')
            ->html(
                self::renderNavButton(
                    $snapshot->isCursor,
                    'first',
                    $snapshot->isCursor || $snapshot->onFirst,
                    $snapshot->firstUrl,
                    'First (top of list)',
                    $snapshot->isCursor ? 'First (top of list)' : 'First captured request',
                    $iconFirst,
                ),
                self::renderNavButton(
                    $snapshot->isCursor,
                    'prev',
                    $snapshot->isCursor ? true : $snapshot->hasPrev === false,
                    $snapshot->prevUrl,
                    'Previous (newer)',
                    $snapshot->isCursor ? 'Previous (newer)' : 'Previous request',
                    $iconPrev,
                ),
                self::renderNavButton(
                    $snapshot->isCursor,
                    'next',
                    $snapshot->isCursor ? false : $snapshot->hasNext === false,
                    $snapshot->nextUrl,
                    'Next (older)',
                    $snapshot->isCursor ? 'Next (older)' : 'Next request',
                    $iconNext,
                ),
                self::renderNavButton(
                    $snapshot->isCursor,
                    'latest',
                    $snapshot->isCursor ? false : $snapshot->onLatest,
                    $snapshot->latestUrl,
                    'Latest (bottom of list)',
                    $snapshot->isCursor ? 'Latest (bottom of list)' : 'Latest captured request',
                    $iconLatest,
                ),
            );
    }

    /**
     * Renders the bottom panel-nav `<nav>` (History + every non-config panel).
     *
     * @param list<SidebarNavItem> $items
     */
    private static function renderPanelNav(array $items): Nav
    {
        $children = [];

        foreach ($items as $item) {
            $classes = ['yii-debug-nav-link'];
            $classes[] = $item->isActive ? 'is-active' : 'yii-debug-nav-link-muted';

            $link = A::tag()
                ->class(implode(' ', $classes))
                ->href(Url::to($item->url))
                ->title($item->tooltip);

            if ($item->isActive) {
                $link = $link->addAriaAttribute('current', 'page');
            }

            $linkChildren = [];

            if ($item->iconSvg !== '') {
                $linkChildren[] = Span::tag()
                    ->class('yii-debug-nav-link-icon')
                    ->addAttribute('aria-hidden', 'true')
                    ->html($item->iconSvg);
            }

            $linkChildren[] = Span::tag()
                ->class('yii-debug-nav-link-label')
                ->content($item->label);

            $children[] = $link->html(...$linkChildren);
        }

        return Nav::tag()
            ->class('yii-debug-nav yii-debug-nav-iconed')
            ->addAriaAttribute('label', 'Debug panels')
            ->html(...$children);
    }

    /**
     * Renders the top snapshot section (`<section>` with header + history card).
     */
    private static function renderSnapshotSection(SidebarSnapshot $snapshot): Section
    {
        $section = Section::tag()
            ->class('yii-debug-side-section yii-debug-request-nav')
            ->addAriaAttribute('label', $snapshot->ariaLabel);

        if ($snapshot->isCursor) {
            $section = $section->addDataAttribute('yii-debug-history-cursor', true);

            if ($snapshot->cursorInitTag !== '') {
                $section = $section->addDataAttribute('yii-debug-cursor-init', $snapshot->cursorInitTag);
            }
        }

        return $section->html(
            Header::tag()->class('yii-debug-side-section-title')->content($snapshot->title),
            self::renderHistoryCard($snapshot),
        );
    }
}
