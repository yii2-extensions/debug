<?php

declare(strict_types=1);

namespace yii\debug\tests\widgets\shell;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\debug\Module;
use yii\debug\panels\{ConfigPanel, RequestPanel};
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\shell\{ShellContext, ShellDataNormalizer};

/**
 * Unit tests for {@see ShellDataNormalizer} covering the narrowing of the loose `$shellData` payload, mode detection,
 * theme attribute derivation, peak-memory formatting and the Configuration-chip URL composition.
 */
#[Group('panel')]
#[Group('shell')]
final class ShellDataNormalizerTest extends TestCase
{
    public function testFromParamsBuildsBareContextForUnknownMode(): void
    {
        $context = ShellDataNormalizer::fromParams(
            'garbage',
            [],
            '',
            null,
        );

        self::assertSame(
            ShellContext::MODE_BARE,
            $context->mode,
            'Unknown mode must collapse to bare.',
        );
        self::assertFalse(
            $context->useShell,
            'Bare mode must skip the shell.',
        );
        self::assertSame(
            'Yii Debugger',
            $context->title,
            'Missing module must yield the default title.',
        );
    }

    public function testFromParamsBuildsViewContextWithPanelsAndManifest(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug', null, ['dataPath' => '@runtime/debug']);

        $module->allowedIPs = ['*'];

        Yii::$app->setModule('debug', $module);
        $module->bootstrap(Yii::$app);

        $context = ShellDataNormalizer::fromParams(
            'view',
            [
                'panels' => ['request' => new RequestPanel()],
                'manifest' => ['tag-1' => ['method' => 'GET']],
                'summary' => ['peakMemory' => 1048576 * 4],
                'tag' => 'tag-1',
                'themeIconSun' => '<svg/>',
                'themeIconMoon' => '<svg/>',
                'debugTheme' => 'dark',
            ],
            'dark',
            $module,
        );

        self::assertTrue(
            $context->useShell,
            'View mode must enable the shell.',
        );
        self::assertSame(
            ShellContext::MODE_VIEW,
            $context->mode,
            'View mode must be detected from the mode string.',
        );
        self::assertArrayHasKey(
            'request',
            $context->shellPanels,
            'Panels map must surface the Request panel.',
        );
        self::assertSame(
            'tag-1',
            $context->activeTag,
            'Active tag must round-trip.',
        );
        self::assertSame(
            '4.00 MB',
            $context->peakMemory,
            "Peak memory must format as 'X.XX MB'.",
        );
        self::assertSame(
            'dark',
            $context->resolvedTheme,
            'Resolved theme must respect the shell data override.',
        );
    }

    public function testFromParamsDropsConfigUrlWhenManifestIsEmpty(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug', null, ['dataPath' => '@runtime/debug']);

        $module->allowedIPs = ['*'];

        Yii::$app->setModule('debug', $module);

        $module->bootstrap(Yii::$app);

        $context = ShellDataNormalizer::fromParams(
            'view',
            ['panels' => []],
            '',
            $module,
        );

        self::assertNull(
            $context->configUrl,
            "Empty manifest must collapse the Config URL to 'null'.",
        );
    }

    public function testFromParamsDropsConfigUrlWhenModuleIsNull(): void
    {
        $this->mockWebApplication();

        $context = ShellDataNormalizer::fromParams(
            'view',
            ['manifest' => ['tag-1' => []]],
            '',
            null,
        );

        self::assertNull(
            $context->configUrl,
            "Missing module must collapse the Config URL to 'null'.",
        );
    }

    public function testFromParamsEmitsDarkThemeAttribute(): void
    {
        $context = ShellDataNormalizer::fromParams(
            'bare',
            [],
            'dark',
            null,
        );

        self::assertSame(
            ['data-yii-debug-theme' => 'dark'],
            $context->debugThemeAttributes,
            'Dark theme must surface as a data attribute.',
        );
    }

    public function testFromParamsFallsBackToYiiVersionWhenConfigPanelMissing(): void
    {
        $this->mockWebApplication();

        $context = ShellDataNormalizer::fromParams(
            'view',
            ['panels' => []],
            '',
            null,
        );

        self::assertSame(
            Yii::getVersion(),
            $context->yiiVersion,
            'Yii version must fall back to the runtime constant when ConfigPanel is missing.',
        );
        self::assertSame(
            PHP_VERSION,
            $context->phpVersion,
            "'PHP' version must fall back to the runtime constant when ConfigPanel is missing.",
        );
    }

    public function testFromParamsNarrowsManifestEntriesToStringKeys(): void
    {
        $context = ShellDataNormalizer::fromParams(
            'index',
            [
                'manifest' => [
                    'tag-1' => ['method' => 'GET'],
                    0 => ['method' => 'POST'],
                ],
            ],
            '',
            null,
        );

        self::assertArrayHasKey(
            'tag-1',
            $context->shellManifest,
            'String-keyed manifest entries must survive narrowing.',
        );
        self::assertArrayNotHasKey(
            '0',
            $context->shellManifest,
            'Numeric-keyed manifest entries must be dropped.',
        );
    }

    public function testFromParamsNarrowsPanelsToPanelInstancesWithStringKeys(): void
    {
        $context = ShellDataNormalizer::fromParams(
            'view',
            [
                'panels' => [
                    'request' => new RequestPanel(),
                    'invalid' => 'not-a-panel',
                    0 => new RequestPanel(),
                ],
            ],
            '',
            null,
        );

        self::assertArrayHasKey(
            'request',
            $context->shellPanels,
            'Valid Panel entries must surface.',
        );
        self::assertArrayNotHasKey(
            'invalid',
            $context->shellPanels,
            'Non-Panel entries must be dropped.',
        );
        self::assertArrayNotHasKey(
            '0',
            $context->shellPanels,
            'Numeric-keyed entries must be dropped.',
        );
    }

    public function testFromParamsOmitsThemeAttributeForUnknownTheme(): void
    {
        $context = ShellDataNormalizer::fromParams(
            'bare',
            [],
            'unknown-theme',
            null,
        );

        self::assertSame(
            [],
            $context->debugThemeAttributes,
            'Unknown theme must drop the data attribute (CSS falls back to media query).',
        );
    }

    public function testFromParamsPluckYiiVersionFromConfigPanelData(): void
    {
        $this->mockWebApplication();

        $config = new ConfigPanel();

        $config->data = [
            'application' => ['yii' => '22.0.x-dev'],
            'php' => ['version' => '8.5.3'],
        ];

        $context = ShellDataNormalizer::fromParams(
            'view',
            ['panels' => ['config' => $config]],
            '',
            null,
        );

        self::assertSame(
            '22.0.x-dev',
            $context->yiiVersion,
            'Yii version must come from the ConfigPanel data when present.',
        );
        self::assertSame(
            '8.5.3',
            $context->phpVersion,
            "'PHP' version must come from the ConfigPanel data when present.",
        );
    }
}
