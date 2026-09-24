<?php

declare(strict_types=1);

namespace yii\debug\service;

use PHPForge\Debug\Helper\{Format, Icon};
use PHPForge\Debug\Storage\RequestSummary;
use PHPForge\Debug\View\Sidebar\SidebarView;
use Yii;
use yii\debug\Module;
use yii\debug\panels\ConfigPanel;
use yii\debug\widgets\shell\ShellContext;
use yii\helpers\Url;

use function array_key_first;

/**
 * Builds the page shell of the debugger actions: the bare shell installed before an action runs, and the full shell
 * with brand bar and sidebar of the history, panel, and standalone pages.
 */
class ShellContextFactory
{
    /**
     * @param Module $module Debug module read for the page title and the Configuration panel at call time.
     */
    public function __construct(protected readonly Module $module) {}

    /**
     * Returns the shell that echoes raw content without the brand bar or sidebar.
     *
     * @param string $theme Resolved light/dark theme.
     *
     * @return ShellContext Bare shell.
     */
    public function bare(string $theme): ShellContext
    {
        return new ShellContext(
            mode: ShellContext::MODE_BARE,
            useShell: false,
            title: $this->module->htmlTitle(),
            debugThemeAttributes: self::themeAttributes($theme),
            resolvedTheme: $theme,
            themeIconSun: '',
            themeIconMoon: '',
            yiiVersion: '',
            phpVersion: '',
            peakMemory: null,
            configUrl: null,
            sidebar: null,
        );
    }

    /**
     * Returns the shell of an index, view, or standalone page.
     *
     * The brand bar reads the Yii and PHP versions the Configuration panel captured, falling back to the running ones,
     * and links the Configuration chip to the focused capture, or to the newest one when no capture is focused.
     *
     * @param string $mode One of the {@see ShellContext} mode constants.
     * @param string $theme Resolved light/dark theme.
     * @param array<string, RequestSummary> $manifest Manifest entries indexed by tag, newest first.
     * @param string|null $activeTag Tag focused by the page, or `null` on the index.
     * @param RequestSummary|null $summary Summary of the focused entry, or `null` on the index.
     * @param SidebarView $sidebar Prepared sidebar payload.
     *
     * @return ShellContext Shell for the requested page.
     */
    public function forSnapshot(
        string $mode,
        string $theme,
        array $manifest,
        string|null $activeTag,
        RequestSummary|null $summary,
        SidebarView $sidebar,
    ): ShellContext {
        $configPanel = $this->module->panels['config'] ?? null;

        $yiiVersion = $configPanel instanceof ConfigPanel ? $configPanel->getYiiVersion() : null;
        $phpVersion = $configPanel instanceof ConfigPanel ? $configPanel->getPhpVersion() : null;

        $configTag = $activeTag ?? array_key_first($manifest);
        $peakMemory = $summary?->peakMemory;

        return new ShellContext(
            mode: $mode,
            useShell: true,
            title: $this->module->htmlTitle(),
            debugThemeAttributes: self::themeAttributes($theme),
            resolvedTheme: $theme,
            themeIconSun: Icon::render('sun'),
            themeIconMoon: Icon::render('moon'),
            yiiVersion: $yiiVersion ?? Yii::getVersion(),
            phpVersion: $phpVersion ?? PHP_VERSION,
            peakMemory: $peakMemory !== null ? Format::bytesToMb($peakMemory) : null,
            configUrl: $configTag === null
                ? null
                : Url::to(Module::route('view', ['panel' => 'config', 'tag' => $configTag])),
            sidebar: $sidebar,
        );
    }

    /**
     * Returns the `<html>` attributes carrying the document language and the resolved theme.
     *
     * @param string $theme Resolved light/dark theme.
     *
     * @return array<string, string> Document attributes.
     */
    private static function themeAttributes(string $theme): array
    {
        return ['lang' => 'en', 'data-yii-debug-theme' => $theme];
    }
}
