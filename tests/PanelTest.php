<?php

declare(strict_types=1);

namespace yii\debug\tests;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\{Module, Panel};
use yii\debug\tests\support\stub\CustomPanel;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see Panel} covering trace-line rendering, the `getToolbarData` template flow, the `getToolbarIcon`
 * and `hasRequestNavigation` extension hooks.
 */
#[Group('panel')]
final class PanelTest extends TestCase
{
    public function testGetToolbarDataFallsBackToSummaryHtmlWhenNoItems(): void
    {
        $panel = $this->makeCustomPanel('custom');

        $panel->stubName = 'Custom';
        $panel->stubSummary = '<strong>Custom summary</strong>';

        $data = $panel->getToolbarData();

        self::assertArrayHasKey(
            'title',
            $data,
            "Toolbar envelope must expose a 'title' key.",
        );
        self::assertArrayHasKey(
            'html',
            $data,
            "Toolbar envelope must expose a 'html' key.",
        );
        self::assertArrayHasKey(
            'url',
            $data,
            "Toolbar envelope must expose a 'url' key.",
        );
        self::assertSame(
            'Custom',
            $data['title'],
            'Title should mirror the panel name.',
        );
        self::assertSame(
            '<strong>Custom summary</strong>',
            $data['html'],
            "Empty 'getToolbarItems' should fall back to the summary HTML.",
        );
        self::assertIsString(
            $data['url'],
            'URL value must be a string.',
        );
        self::assertStringContainsString(
            'panel=custom',
            $data['url'],
            'URL should target the panel by id.',
        );
    }

    public function testGetToolbarDataIncludesIconKeyWhenPanelDeclaresOne(): void
    {
        $panel = $this->makeCustomPanel('hot');

        $panel->stubName = 'Hot';
        $panel->stubIcon = 'profiling';
        $panel->stubItems = [['value' => 42]];

        $data = $panel->getToolbarData();

        self::assertArrayHasKey(
            'icon',
            $data,
            "Toolbar envelope must expose an 'icon' key when declared.",
        );
        self::assertSame(
            'profiling',
            $data['icon'],
            'Icon key should round-trip into the toolbar JSON envelope.',
        );
    }

    public function testGetToolbarDataOmitsIconKeyByDefault(): void
    {
        $panel = $this->makeCustomPanel('plain');

        $panel->stubName = 'Plain';
        $panel->stubItems = [['value' => 1]];

        self::assertArrayNotHasKey(
            'icon',
            $panel->getToolbarData(),
            "Panels that do not declare a toolbar icon must not emit an 'icon' key.",
        );
    }

    public function testGetToolbarDataReturnsEmptyArrayWhenItemsAreNull(): void
    {
        $panel = $this->makeCustomPanel('silent');

        $panel->stubName = 'Silent';
        $panel->stubItems = null;

        self::assertSame(
            [],
            $panel->getToolbarData(),
            "Returning 'null' from 'getToolbarItems' hides the chip entirely.",
        );
    }

    public function testGetToolbarIconDefaultsToNull(): void
    {
        self::assertNull(
            $this->createPanel()->getToolbarIcon(),
            'Base Panel exposes no toolbar icon.',
        );
    }

    public function testGetTraceLineAcceptsClosureTemplate(): void
    {
        [$panel, $module] = $this->createPanelWithModule();

        $module->traceLine = static fn(): string => 'http://my.custom.link';

        self::assertSame(
            'http://my.custom.link',
            $panel->getTraceLine(['file' => 'file.php', 'line' => 10]),
            'Closure traceLine result should be returned as-is.',
        );
    }

    public function testGetTraceLineAcceptsClosureTemplateWithCustomText(): void
    {
        [$panel, $module] = $this->createPanelWithModule();

        $module->traceLine = static fn(): string => '<a href="ide://open?url={file}&line={line}">{text}</a>';

        self::assertSame(
            '<a href="ide://open?url=file.php&line=10">custom text</a>',
            $panel->getTraceLine(['file' => 'file.php', 'line' => 10, 'text' => 'custom text']),
            "Closure-returned templates should still resolve '{file}/{line}/{text}' placeholders.",
        );
    }

    public function testGetTraceLineAcceptsStringTemplate(): void
    {
        [$panel, $module] = $this->createPanelWithModule();

        $module->traceLine = '<a href="phpstorm://open?url=file://{file}&line={line}">my custom phpstorm protocol</a>';

        self::assertStringContainsString(
            'phpstorm://open',
            $panel->getTraceLine(['file' => 'file.php', 'line' => 10]),
            'Custom traceLine string should be honored verbatim with placeholder substitution.',
        );
    }

    public function testGetTraceLineFallsBackToPlainTextWhenTraceLineDisabled(): void
    {
        [$panel, $module] = $this->createPanelWithModule();

        $module->traceLine = false;

        self::assertSame(
            'file.php:10',
            $panel->getTraceLine(['file' => 'file.php', 'line' => 10]),
            "Disabled traceLine should emit plain 'file:line' text without anchor markup.",
        );
    }

    public function testGetTraceLineRendersDefaultIdeLink(): void
    {
        $panel = $this->createPanel();

        $line = $panel->getTraceLine(['file' => 'file.php', 'line' => 10]);

        self::assertSame(
            '<a href="ide://open?url=file://file.php&line=10">file.php:10</a>',
            $line,
            'Default trace line should expose an IDE-protocol anchor with file:line text.',
        );
    }

    public function testGetTraceLineRewritesPathViaTracePathMappings(): void
    {
        [$panel, $module] = $this->createPanelWithModule();

        $module->tracePathMappings = ['/app' => '/newpath/'];

        self::assertSame(
            '<a href="ide://open?url=file:///newpath/file.php&line=10">/app/file.php:10</a>',
            $panel->getTraceLine(['file' => '/app/file.php', 'line' => 10]),
            "'tracePathMappings' should rewrite the URL path while keeping the displayed text intact.",
        );
    }

    public function testGetTraceLineUsesCustomTextWhenProvided(): void
    {
        $panel = $this->createPanel();

        $line = $panel->getTraceLine(
            [
                'file' => 'file.php',
                'line' => 10,
                'text' => 'custom text',
            ],
        );

        self::assertSame(
            '<a href="ide://open?url=file://file.php&line=10">custom text</a>',
            $line,
            "Custom text should replace the default 'file:line' anchor body.",
        );
    }

    public function testGetTraceLineUsesFirstMatchingPathMapping(): void
    {
        [$panel, $module] = $this->createPanelWithModule();

        $module->tracePathMappings = [
            '/app/data' => '/app/localdata',
            '/app' => '/newpath',
        ];

        self::assertSame(
            '<a href="ide://open?url=file:///app/localdata/file.php&line=10">/app/data/file.php:10</a>',
            $panel->getTraceLine(['file' => '/app/data/file.php', 'line' => 10]),
            "Only the first matching key in 'tracePathMappings' should be applied.",
        );
    }

    public function testHasRequestNavigationDefaultsToTrue(): void
    {
        self::assertTrue(
            $this->createPanel()->hasRequestNavigation(),
            'Default panels participate in request navigation.',
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockWebApplication();
    }

    private function createPanel(): Panel
    {
        return $this->createPanelWithModule()[0];
    }

    /**
     * @return array{0: Panel, 1: Module}
     */
    private function createPanelWithModule(): array
    {
        $module = new Module('debug');

        return [new Panel(['module' => $module]), $module];
    }

    private function makeCustomPanel(string $id): CustomPanel
    {
        return new CustomPanel(['id' => $id, 'tag' => 'test-tag', 'module' => new Module('debug')]);
    }
}
