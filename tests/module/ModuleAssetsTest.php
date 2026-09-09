<?php

declare(strict_types=1);

namespace yii\debug\tests\module;

use PHPForge\Debug\Helper\Icon;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Yii;
use yii\debug\{DebugAsset, Module, ToolbarAsset};
use yii\debug\exception\Message;
use yii\debug\tests\support\ModuleTestCase;

use function base64_encode;

/**
 * Unit tests for {@see Module}, {@see DebugAsset}, and {@see ToolbarAsset} covering shared script/stylesheet paths,
 * ES-module configuration, focus-runtime availability, and Yii logo resolution, overrides, and missing-asset errors.
 */
#[Group('module')]
final class ModuleAssetsTest extends ModuleTestCase
{
    public function testDebugAssetShipsLocalFrameworkAgnosticScript(): void
    {
        $asset = new DebugAsset();

        self::assertSame(
            ['dist/js/debug.min.js'],
            $asset->js,
            'DebugAsset must ship one consolidated panel script.',
        );
        self::assertSame(
            'module',
            $asset->jsOptions['type'] ?? null,
            'Panel script must load as an ES module.',
        );
        self::assertSame(
            Yii::getAlias(Module::SOURCE_PATH),
            $asset->sourcePath,
            'DebugAsset must publish the framework-neutral core frontend.',
        );
    }

    public function testDebugAssetShipsSingleMainStylesheet(): void
    {
        $asset = new DebugAsset();

        self::assertSame(
            ['dist/css/debug.min.css'],
            $asset->css,
            'DebugAsset must ship one consolidated stylesheet.',
        );
    }

    public function testDebugAssetsShipSharedFocusRuntime(): void
    {
        self::assertFileExists(
            Yii::getAlias(Module::SOURCE_PATH) . '/dist/js/focus.min.js',
            'Published shared assets must include the toolbar keyboard-focus runtime.',
        );
    }

    public function testGetYiiLogoUsesSharedFrontendAsset(): void
    {
        // Reset the static cache so this test always exercises the lazy data-URI composition.
        $this->setInaccessibleStaticProperty(Module::class, 'yiiLogo', null);

        self::assertSame(
            'data:image/svg+xml;base64,' . base64_encode(Icon::render('yii')),
            Module::getYiiLogo(),
            'Shared Yii logo URI must be returned.',
        );
    }

    public function testSetAndGetYiiLogoRoundTrip(): void
    {
        Module::setYiiLogo('data:image/svg+xml;base64,FAKE');

        self::assertSame(
            'data:image/svg+xml;base64,FAKE',
            Module::getYiiLogo(),
            'Configured URI must round-trip.',
        );

        // Reset cache so other tests see the bundled logo path again.
        $this->setInaccessibleStaticProperty(Module::class, 'yiiLogo', null);
    }

    public function testThrowRuntimeExceptionWhenSharedYiiLogoIsUnavailable(): void
    {
        $this->setInaccessibleStaticProperty(Icon::class, 'cache', ['yii' => '']);
        $this->setInaccessibleStaticProperty(Module::class, 'yiiLogo', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            Message::YII_LOGO_UNREADABLE->getMessage(),
        );

        try {
            Module::getYiiLogo();

            self::fail(
                'A missing packaged Yii logo must raise an explicit runtime error.',
            );
        } catch (RuntimeException $exception) {
            self::assertSame(
                Message::YII_LOGO_UNREADABLE->getMessage(),
                $exception->getMessage(),
                'A missing packaged Yii logo must report the failing asset boundary.',
            );

            throw $exception;
        } finally {
            $this->setInaccessibleStaticProperty(Icon::class, 'cache', []);
            $this->setInaccessibleStaticProperty(Module::class, 'yiiLogo', null);
        }
    }

    public function testToolbarAssetShipsSharedRuntimeAsEsModule(): void
    {
        $asset = new ToolbarAsset();

        self::assertSame(
            ['dist/js/toolbar.min.js'],
            $asset->js,
            'ToolbarAsset must ship one consolidated runtime script.',
        );
        self::assertSame(
            'module',
            $asset->jsOptions['type'] ?? null,
            'Runtime script must load as an ES module.',
        );
        self::assertSame(
            Yii::getAlias(Module::SOURCE_PATH),
            $asset->sourcePath,
            'ToolbarAsset must publish the framework-neutral core frontend.',
        );
    }
}
