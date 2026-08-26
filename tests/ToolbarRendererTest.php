<?php

declare(strict_types=1);

namespace yii\debug\tests;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\debug\{Module, ToolbarRenderer};
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see ToolbarRenderer} markup injection and toolbar-element rendering defaults.
 */
#[Group('toolbar')]
final class ToolbarRendererTest extends TestCase
{
    public function testInjectInsertsToolbarBeforeClosingBodyWithoutConsumingMarkup(): void
    {
        $renderer = $this->createRenderer();

        self::assertSame(
            '<html><body>X<!--T--></body></html>',
            $renderer->inject('<html><body>X</body></html>', '<!--T-->'),
            'Toolbar must land before `</body>` without replacing any characters.',
        );
        self::assertSame(
            '<p>No body</p><!--T-->',
            $renderer->inject('<p>No body</p>', '<!--T-->'),
            'Missing `</body>` must append the toolbar.',
        );
    }

    public function testRenderElementAppliesDefaultPositionAndHeightAndTrimsOutput(): void
    {
        $html = $this->createRenderer()->renderElement('/debug/toolbar-data?tag=t');

        self::assertStringContainsString('data-position="bottom"', $html, 'Position must default to `bottom`.');
        self::assertStringContainsString('data-height="50"', $html, 'Height must default to `50`.');
        self::assertStringStartsWith('<yii-debug-toolbar', $html, 'Leading whitespace must be trimmed.');
        self::assertStringEndsWith('</yii-debug-toolbar>', $html, 'Trailing template newline must be trimmed.');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockWebApplication();
    }

    private function createRenderer(): ToolbarRenderer
    {
        // Instantiating the module registers the shared Debug Core view alias used by the renderer.
        new Module('debug');

        return new ToolbarRenderer(
            Yii::$app->getView(),
            Yii::$app->getAssetManager(),
            Module::VIEW_PATH_ALIAS,
        );
    }
}
