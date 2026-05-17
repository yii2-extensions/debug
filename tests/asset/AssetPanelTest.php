<?php

declare(strict_types=1);

namespace yii\debug\tests\asset;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\debug\{DebugAsset, LogTarget, Module};
use yii\debug\panels\AssetPanel;
use yii\debug\tests\support\TestCase;
use yii\web\AssetBundle;

/**
 * Unit tests for {@see AssetPanel} covering `getName`/`getToolbarIcon`, the toolbar-items chip with bundle count
 * (and the `null` short-circuit when no bundles), `getDetail`/`getSummary` rendering, `isEnabled` resolution, the
 * `save()` snapshot path (including the `format()` URL wrapping, `formatOptions()` stringification, and the
 * `serializeOptions()` closure-to-label substitution).
 */
#[Group('asset')]
#[Group('panel')]
final class AssetPanelTest extends TestCase
{
    public function testFormatOptionsStringifiesScalarsAndDebugTypesOtherValues(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        $formatted = $this->invoke(
            $panel,
            'formatOptions',
            [['flag' => true, 'callback' => static fn(): bool => true, 'name' => 'debug']],
        );

        self::assertIsArray(
            $formatted,
            'formatOptions() must return an array.',
        );

        $flag = $formatted['flag'] ?? '';
        $callback = $formatted['callback'] ?? '';
        $name = $formatted['name'] ?? '';

        self::assertIsString(
            $flag,
            'Flag entry must be stringified.',
        );
        self::assertIsString(
            $callback,
            'Callback entry must be stringified.',
        );
        self::assertIsString(
            $name,
            'Name entry must be stringified.',
        );
        self::assertStringContainsString(
            '1',
            $flag,
            "Boolean 'true' must surface as '1'.",
        );
        self::assertStringContainsString(
            'Closure',
            $callback,
            "Non-scalar values must surface via 'get_debug_type()'.",
        );
        self::assertStringContainsString(
            'debug',
            $name,
            'String values must round-trip verbatim.',
        );
    }

    public function testFormatWrapsCssAndJsFilesInAnchorsBoundToBaseUrl(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        $bundle = new AssetBundle();

        $bundle->baseUrl = '/assets/debug';
        $bundle->css = ['style.css'];
        $bundle->js = ['script.js'];

        $formatted = $this->invoke(
            $panel,
            'format',
            [['debug' => $bundle]],
        );

        self::assertIsArray(
            $formatted,
            "'format()' must return an array.",
        );
        self::assertInstanceOf(
            AssetBundle::class,
            $formatted['debug'] ?? null,
            'Bundle must round-trip.',
        );

        $css = $formatted['debug']->css[0] ?? '';
        $js = $formatted['debug']->js[0] ?? '';

        self::assertIsString(
            $css,
            'CSS entry must be stringified.',
        );
        self::assertIsString(
            $js,
            'JS entry must be stringified.',
        );
        self::assertStringContainsString(
            'href="/assets/debug/style.css"',
            $css,
            'CSS link must point at baseUrl + file.',
        );
        self::assertStringContainsString(
            'href="/assets/debug/script.js"',
            $js,
            'JS link must point at baseUrl + file.',
        );
    }

    public function testGetDetailRendersBundleSummary(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        $panel->data = [
            DebugAsset::class => [
                'basePath' => '/tmp',
                'baseUrl' => '/assets/debug',
                'css' => [],
                'cssOptions' => [],
                'depends' => [],
                'js' => ['debug.min.js'],
                'jsOptions' => [],
                'publishOptions' => [],
                'sourcePath' => '/src/assets',
            ],
        ];

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'DebugAsset',
            $html,
            'Detail view must surface the bundle FQCN.',
        );
    }

    public function testGetNameAndIconReturnConstantsForToolbar(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        self::assertSame(
            'Asset Bundles',
            $panel->getName(),
            "Panel name must be 'Asset Bundles'.",
        );
        self::assertSame(
            'asset',
            $panel->getToolbarIcon(),
            "Toolbar icon must be 'asset'.",
        );
    }

    public function testGetSummaryRendersToolbarChip(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        $panel->data = ['BundleA' => []];

        self::assertNotSame(
            '',
            $panel->getSummary(),
            'Summary HTML must render when bundles are present.',
        );
    }

    public function testGetToolbarItemsEmitsInfoChipWithBundleCount(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        $panel->data = [
            'BundleA' => [],
            'BundleB' => [],
        ];

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Toolbar items must surface as a list.',
        );
        self::assertIsArray(
            $items[0] ?? null,
            'First chip must be an array.',
        );
        self::assertSame(
            2,
            $items[0]['value'] ?? null,
            "Chip 'value' must match the bundle count.",
        );
        self::assertSame(
            'info',
            $items[0]['status'] ?? null,
            "Chip 'status' must be 'info'.",
        );
    }

    public function testGetToolbarItemsReturnsNullWhenNoBundles(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        $panel->data = [];

        self::assertNull(
            $this->invoke($panel, 'getToolbarItems'),
            "Empty bundle list must collapse the toolbar chip to 'null'.",
        );
    }

    public function testIsEnabledFalseWhenAssetManagerComponentIsMissing(): void
    {
        $this->mockWebApplication();

        Yii::$app->setComponents(['assetManager' => null]);

        $module = new Module('debug');

        $module->logTarget = new LogTarget($module);

        $panel = new AssetPanel();

        $panel->module = $module;

        self::assertFalse(
            $panel->isEnabled(),
            "Missing 'assetManager' component must collapse 'isEnabled()' to 'false'.",
        );
    }

    public function testIsEnabledTrueWhenAssetManagerResolves(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        self::assertTrue(
            $panel->isEnabled(),
            'Default mocked app must expose an asset manager.',
        );
    }

    public function testSaveReturnsEmptyArrayWhenNoBundlesRegistered(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        Yii::$app->getAssetManager()->bundles = [];

        self::assertSame(
            [],
            $panel->save(),
            'No registered bundles must yield an empty snapshot.',
        );
    }

    public function testSaveSerializesRegisteredBundleAndReplacesClosureCallbacks(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        $bundle = new AssetBundle();

        $bundle->basePath = '/tmp/base';
        $bundle->baseUrl = '/assets/debug';
        $bundle->css = ['style.css'];
        $bundle->js = ['script.js'];
        $bundle->sourcePath = '/src/assets';
        $bundle->publishOptions = [
            'beforeCopy' => static fn(): bool => true,
            'afterCopy' => static fn(): bool => true,
            'forceCopy' => true,
        ];

        Yii::$app->getAssetManager()->bundles = ['debug' => $bundle];

        $snapshot = $panel->save();

        self::assertArrayHasKey(
            'debug',
            $snapshot,
            'Snapshot must include the registered bundle.',
        );
        self::assertSame(
            '\Closure',
            $snapshot['debug']['publishOptions']['beforeCopy'] ?? null,
            "'beforeCopy' closure must be replaced with the '\\Closure' label.",
        );
        self::assertSame(
            '\Closure',
            $snapshot['debug']['publishOptions']['afterCopy'] ?? null,
            "'afterCopy' closure must be replaced with the '\\Closure' label.",
        );
        self::assertTrue(
            $snapshot['debug']['publishOptions']['forceCopy'] ?? false,
            'Non-closure publishOptions entries must round-trip verbatim.',
        );
    }

    public function testSaveSkipsNonStringKeysAndNonAssetBundleEntries(): void
    {
        $panel = $this->makePanel(AssetPanel::class);

        $bundle = new AssetBundle();

        $bundle->baseUrl = '/assets/debug';
        $bundle->css = ['style.css'];

        Yii::$app->getAssetManager()->bundles = [
            'debug' => $bundle,
            0 => $bundle,                 // non-string key, must be skipped
            'invalid' => new \stdClass(), // non-AssetBundle value, must be skipped
        ];

        $snapshot = $panel->save();

        self::assertArrayHasKey(
            'debug',
            $snapshot,
            'Valid string-keyed bundle must surface.',
        );
        self::assertArrayNotHasKey(
            0,
            $snapshot,
            'Numeric keys must be filtered out.',
        );
        self::assertArrayNotHasKey(
            'invalid',
            $snapshot,
            "Non-'AssetBundle' values must be filtered out.",
        );
    }
}
