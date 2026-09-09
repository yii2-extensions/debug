<?php

declare(strict_types=1);

namespace yii\debug\tests\actions;

use LogicException;
use PHPForge\Debug\Panel\Config\ConfigSnapshot;
use PHPForge\Debug\Storage\RequestSummary;
use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\debug\actions\Action;
use yii\debug\actions\PhpInfoAction;
use yii\debug\actions\ViewAction;
use yii\debug\exception\Message;
use yii\debug\Module;
use yii\debug\panels\ConfigPanel;
use yii\debug\tests\support\ActionTestCase;
use yii\debug\widgets\shell\ShellContext;
use yii\debug\widgets\sidebar\SidebarView;
use yii\helpers\Url;
use yii\web\Response;

/**
 * Unit tests for {@see Action} covering `beforeRun` HTML format and bare-shell setup, `createShellContext` metadata
 * and configuration links, `resolveTheme` query/cookie/default selection, and shared layout rendering with peak memory,
 * a disabled configuration chip, and missing-context rejection.
 */
#[Group('actions')]
final class ActionShellTest extends ActionTestCase
{
    public function testBeforeRunForcesHtmlResponseFormatAndBareShell(): void
    {
        $module = $this->bootDebugModule();

        $this->runDebugAction(
            new PhpInfoAction('php-info'),
            $module,
        );

        self::assertSame(
            Response::FORMAT_HTML,
            Yii::$app->response->format,
            'HTML response format must be enforced.',
        );

        $shell = Yii::$app->view->params['debugShell'] ?? null;

        self::assertInstanceOf(
            ShellContext::class,
            $shell,
            'A typed bare shell context must be installed.',
        );
        self::assertFalse(
            $shell->useShell,
            'Bare action context must not render the full debug shell.',
        );
        self::assertArrayHasKey(
            'lang',
            $shell->debugThemeAttributes,
            'Bare shell document attributes must declare a language.',
        );
    }

    public function testCreateShellContextSupportsEmptyManifestAndNumericPeakMemory(): void
    {
        $module = $this->bootDebugModule();

        $configPanel = $module->panels['config'] ?? null;

        self::assertInstanceOf(
            ConfigPanel::class,
            $configPanel,
            'Config panel must be wired.',
        );

        $this->hydratePanel(
            $configPanel,
            ConfigSnapshot::capture(
                [
                    'application' => ['yii' => 'captured-yii-version'],
                    'php' => ['version' => 'captured-php-version'],
                ],
            ),
        );

        $action = new ViewAction('view');

        $summary = RequestSummary::fromArray(
            [
                'tag' => 'tag-shell',
                'url' => 'dummy',
                'ajax' => false,
                'method' => 'GET',
                'ip' => '127.0.0.1',
                'time' => 1_700_000_000.0,
                'statusCode' => 200,
                'sqlCount' => 0,
                'excessiveCallersCount' => 0,
                'mailCount' => 0,
                'mailFiles' => [],
                'processingTime' => null,
                'peakMemory' => 1_048_576,
            ],
        );

        $action->setModule($module);

        $context = $this->invoke(
            $action,
            'createShellContext',
            [
                ShellContext::MODE_INDEX,
                [],
                null,
                $summary,
                new SidebarView(null, []),
            ],
        );

        self::assertInstanceOf(
            ShellContext::class,
            $context,
            'The factory must return a typed shell context.',
        );
        self::assertNull(
            $context->configUrl,
            'An empty manifest must disable the configuration link.',
        );
        self::assertNotNull(
            $context->peakMemory,
            'Numeric peak memory must be formatted for the shell header.',
        );
        self::assertSame(
            'captured-yii-version',
            $context->yiiVersion,
            'Shell must prefer the Yii version stored with the debug entry.',
        );
        self::assertSame(
            'captured-php-version',
            $context->phpVersion,
            'Shell must prefer the PHP version stored with the debug entry.',
        );
        self::assertTrue(
            $context->useShell,
            'Index context must render the full debug shell.',
        );
        self::assertArrayHasKey(
            'lang',
            $context->debugThemeAttributes,
            'Full shell document attributes must declare a language.',
        );

        $activeContext = $this->invoke(
            $action,
            'createShellContext',
            [
                ShellContext::MODE_VIEW,
                ['manifest-tag' => $summary],
                'active-tag',
                null,
                new SidebarView(null, []),
            ],
        );

        self::assertInstanceOf(
            ShellContext::class,
            $activeContext,
            'The factory must return a typed active shell context.',
        );
        self::assertSame(
            Url::to(Module::route('view', ['panel' => 'config', 'tag' => 'active-tag'])),
            $activeContext->configUrl,
            'Configuration link must target the explicitly active entry.',
        );
    }

    public function testPrimeThemeContextResolvesDarkFromGlobalCookieFallback(): void
    {
        $module = $this->bootDebugModule();

        // Query is absent and the request component's cookie collection is empty: the $_COOKIE fallback wins.
        $_COOKIE['yii-debug-toolbar-theme'] = 'dark';

        try {
            $action = new ViewAction('view');

            $action->setModule($module);

            $theme = $this->invoke(
                $action,
                'resolveTheme',
            );
        } finally {
            unset($_COOKIE['yii-debug-toolbar-theme']);
        }

        self::assertSame(
            'dark',
            $theme,
            '$_COOKIE fallback must seed the dark theme.',
        );
    }

    public function testPrimeThemeContextResolvesDarkFromQueryParam(): void
    {
        $module = $this->bootDebugModule();

        $_GET['yii_debug_theme'] = 'DARK';

        $action = new ViewAction('view');

        $action->setModule($module);

        $theme = $this->invoke(
            $action,
            'resolveTheme',
        );

        self::assertSame(
            'dark',
            $theme,
            "The 'yii_debug_theme' query value must select the dark theme case-insensitively.",
        );
    }

    public function testPrimeThemeContextResolvesLightThemeByDefault(): void
    {
        $module = $this->bootDebugModule();

        $action = new ViewAction('view');

        $action->setModule($module);

        $theme = $this->invoke(
            $action,
            'resolveTheme',
        );

        self::assertSame(
            'light',
            $theme,
            "Missing theme inputs must default to 'light'.",
        );
    }

    public function testSharedShellRendersPeakMemoryAndDisabledConfigChip(): void
    {
        $module = $this->bootDebugModule();

        $action = new ViewAction('view');

        $action->setModule($module);

        $html = Yii::$app->view->renderFile(
            Module::VIEW_PATH_ALIAS . '/_shell.php',
            [
                'actionIcon' => '<svg/>',
                'actionLabel' => 'Config',
                'actionTitle' => 'No requests captured yet',
                'actionUrl' => null,
                'content' => '<p>Content</p>',
                'debugTheme' => 'light',
                'historyUrl' => '/debug',
                'mode' => 'index',
                'peakMemory' => '1.21 MB',
                'phpIcon' => '<svg/>',
                'phpVersion' => '8.5.0',
                'sidebar' => '<aside>Sidebar</aside>',
                'themeIconMoon' => '<svg/>',
                'themeIconSun' => '<svg/>',
                'useShell' => true,
                'yiiIcon' => '<svg/>',
                'yiiVersion' => '2.0.0',
            ],
        );

        self::assertStringContainsString(
            '1.21 MB',
            $html,
            'Peak memory chip must surface when value is non-null.',
        );
        self::assertStringContainsString(
            'is-disabled',
            $html,
            "Null 'actionUrl' must render the disabled config chip.",
        );
    }

    public function testThrowLogicExceptionWhenMainLayoutHasNoShellContext(): void
    {
        $module = $this->bootDebugModule();

        $action = new ViewAction('view');

        $action->setModule($module);

        unset(Yii::$app->view->params['debugShell']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            Message::SHELL_CONTEXT_REQUIRED->getMessage(),
        );

        Yii::$app->view->renderFile(
            dirname(__DIR__, 2) . '/src/views/layouts/main.php',
            ['content' => ''],
            $action,
        );
    }
}
