<?php

declare(strict_types=1);

namespace yii\debug\widgets\shell;

use Yii;
use yii\base\Module as BaseModule;
use yii\debug\Module as DebugModule;
use yii\debug\Panel;
use yii\helpers\Url;

use function array_key_first;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;
use function strtolower;

/**
 * Narrows the loose `$this->params` payload the debugger layout reads into a typed {@see ShellContext}.
 *
 * Concentrates every defensive {@see is_array()} / {@see is_string()} check and the per-mode branching (panels map
 * narrowing, version pluck from the Config panel data, peak-memory formatting, theme attribute derivation,
 * Configuration-chip URL composition) in one testable place.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final class ShellDataNormalizer
{
    private const BYTES_PER_MB = 1024 * 1024;

    /**
     * Builds the typed shell context.
     */
    public static function fromParams(
        mixed $shellMode,
        mixed $shellData,
        string $debugTheme,
        BaseModule|null $module,
    ): ShellContext {
        $mode = self::normalizeMode($shellMode);

        $useShell = $mode !== ShellContext::MODE_BARE;
        $title = $module instanceof DebugModule ? $module->htmlTitle() : 'Yii Debugger';

        $debugThemeAttributes = in_array($debugTheme, ['dark', 'light'], true)
            ? ['data-yii-debug-theme' => $debugTheme]
            : [];

        if ($useShell === false) {
            return self::buildBareContext($mode, $title, $debugThemeAttributes);
        }

        $shellData = self::narrowShellData($shellData);
        $shellPanels = self::narrowPanels($shellData['panels'] ?? null);
        $shellManifest = self::narrowManifest($shellData['manifest'] ?? null);
        $shellSummary = self::narrowSummary($shellData['summary'] ?? null);

        $configPanel = $shellPanels['config'] ?? null;

        $configData = $configPanel !== null && is_array($configPanel->data) ? $configPanel->data : [];

        $yiiVersion = self::pluckString($configData, ['application', 'yii']) ?? Yii::getVersion();
        $phpVersion = self::pluckString($configData, ['php', 'version']) ?? PHP_VERSION;
        $peakMemory = self::formatPeakMemory($shellSummary);
        $themeIconSun = self::shellString($shellData, 'themeIconSun');
        $themeIconMoon = self::shellString($shellData, 'themeIconMoon');
        $resolvedTheme = self::shellString($shellData, 'debugTheme');

        if ($resolvedTheme === '') {
            $resolvedTheme = $debugTheme === '' ? 'light' : $debugTheme;
        }

        $activeTag = self::nullableString($shellData['tag'] ?? null);

        $activePanel = $shellData['activePanel'] ?? null;
        $activePanel = $activePanel instanceof Panel ? $activePanel : null;

        $configUrl = self::buildConfigUrl($module, $activeTag, $shellManifest);
        $cursorInit = self::shellString($shellData, 'cursorInit');

        return new ShellContext(
            mode: $mode,
            useShell: true,
            title: $title,
            debugThemeAttributes: $debugThemeAttributes,
            resolvedTheme: $resolvedTheme,
            themeIconSun: $themeIconSun,
            themeIconMoon: $themeIconMoon,
            yiiVersion: $yiiVersion,
            phpVersion: $phpVersion,
            peakMemory: $peakMemory,
            configUrl: $configUrl,
            shellPanels: $shellPanels,
            shellManifest: $shellManifest,
            activePanel: $activePanel,
            activeTag: $activeTag,
            shellSummary: $shellSummary,
            cursorInit: $cursorInit,
        );
    }

    /**
     * Resolves the active theme from the request/cookie pair so direct hits on the layout (legacy paths) keep working.
     */
    public static function resolveThemeFromRequest(): string
    {
        $request = Yii::$app->getRequest();

        $rawTheme = $request->get('yii_debug_theme', $request->getCookies()->getValue('yii-debug-toolbar-theme'));

        return is_string($rawTheme) ? strtolower($rawTheme) : '';
    }

    /**
     * @param array<string, string> $themeAttributes
     */
    private static function buildBareContext(string $mode, string $title, array $themeAttributes): ShellContext
    {
        return new ShellContext(
            mode: $mode,
            useShell: false,
            title: $title,
            debugThemeAttributes: $themeAttributes,
            resolvedTheme: '',
            themeIconSun: '',
            themeIconMoon: '',
            yiiVersion: '',
            phpVersion: '',
            peakMemory: null,
            configUrl: null,
            shellPanels: [],
            shellManifest: [],
            activePanel: null,
            activeTag: null,
            shellSummary: null,
            cursorInit: '',
        );
    }

    /**
     * Builds the Configuration-chip URL for the brand bar; falls back to the latest captured tag when no active tag
     * is present and returns `null` when the manifest is empty (chip renders disabled).
     *
     * @param array<string, array<string, mixed>> $manifest
     */
    private static function buildConfigUrl(BaseModule|null $module, string|null $activeTag, array $manifest): string|null
    {
        $tag = $activeTag !== null && $activeTag !== ''
            ? $activeTag
            : ($manifest === [] ? null : array_key_first($manifest));

        if ($tag === null || $module === null) {
            return null;
        }

        return Url::to(
            [
                '/' . $module->getUniqueId() . '/default/view',
                'panel' => 'config',
                'tag' => $tag,
            ],
        );
    }

    /**
     * @param array<string, mixed>|null $summary
     */
    private static function formatPeakMemory(array|null $summary): string|null
    {
        if ($summary === null || !is_numeric($summary['peakMemory'] ?? null)) {
            return null;
        }

        return sprintf('%.2f MB', (float) $summary['peakMemory'] / self::BYTES_PER_MB);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function narrowManifest(mixed $manifest): array
    {
        if (!is_array($manifest)) {
            return [];
        }

        $out = [];

        foreach ($manifest as $tag => $entry) {
            if (!is_string($tag) || !is_array($entry)) {
                continue;
            }

            $stringKeyed = [];

            foreach ($entry as $entryKey => $entryValue) {
                if (is_string($entryKey)) {
                    $stringKeyed[$entryKey] = $entryValue;
                }
            }

            $out[$tag] = $stringKeyed;
        }

        return $out;
    }

    /**
     * @return array<string, Panel>
     */
    private static function narrowPanels(mixed $panels): array
    {
        if (!is_array($panels)) {
            return [];
        }

        $out = [];

        foreach ($panels as $id => $panel) {
            if (is_string($id) && $panel instanceof Panel) {
                $out[$id] = $panel;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function narrowShellData(mixed $shellData): array
    {
        if (!is_array($shellData)) {
            return [];
        }

        $out = [];

        foreach ($shellData as $key => $value) {
            if (is_string($key)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function narrowSummary(mixed $summary): array|null
    {
        if (!is_array($summary)) {
            return null;
        }

        $out = [];

        foreach ($summary as $key => $value) {
            if (is_string($key)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private static function normalizeMode(mixed $mode): string
    {
        if (
            !is_string($mode)
            || in_array($mode, [ShellContext::MODE_VIEW, ShellContext::MODE_INDEX], true) === false
        ) {
            return ShellContext::MODE_BARE;
        }

        return $mode;
    }

    private static function nullableString(mixed $value): string|null
    {
        return is_string($value) ? $value : null;
    }

    /**
     * Walks a nested array following `$path` and returns the first scalar string hit; `null` when any step is missing
     * or the leaf is not a scalar.
     *
     * @param array<int|string, mixed> $data
     * @param list<string> $path
     */
    private static function pluckString(array $data, array $path): string|null
    {
        $cursor = $data;

        foreach ($path as $segment) {
            if (!is_array($cursor) || !isset($cursor[$segment])) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        if (is_string($cursor) && $cursor !== '') {
            return $cursor;
        }

        if (is_numeric($cursor)) {
            return (string) $cursor;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $shellData
     */
    private static function shellString(array $shellData, string $key): string
    {
        $value = $shellData[$key] ?? '';

        return is_string($value) ? $value : '';
    }
}
