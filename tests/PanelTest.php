<?php

declare(strict_types=1);

namespace yii\debug\tests;

use Exception;
use PHPUnit\Framework\Attributes\Group;
use yii\base\InvalidConfigException;
use yii\debug\{FlattenException, LogTarget, Module, Panel};
use yii\debug\tests\support\stub\CustomPanel;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

/**
 * Unit tests for {@see Panel} covering trace-line rendering, the `getToolbarData` template flow, the `getToolbarIcon`
 * and `hasRequestNavigation` extension hooks.
 */
#[Group('panel')]
final class PanelTest extends TestCase
{
    public function testGetDetailDefaultsToEmptyString(): void
    {
        self::assertSame(
            '',
            $this->createPanel()->getDetail(),
            'Base Panel exposes no detail view.',
        );
    }

    public function testGetLogMessagesStringifiesThrowableFirstElement(): void
    {
        [$panel, $module] = $this->createPanelWithModule();

        $logTarget = new LogTarget($module);

        $logTarget->messages = [[new Exception('boom'), Logger::LEVEL_ERROR, 'app', 0.0]];

        $module->logTarget = $logTarget;

        $messages = $this->invoke(
            $panel,
            'getLogMessages',
            [0, [], [], true],
        );

        self::assertIsArray(
            $messages,
            "'getLogMessages' must return a list of log entries.",
        );
        self::assertCount(
            1,
            $messages,
            'Single throwable message must round-trip into the filtered list.',
        );

        self::assertArrayHasKey(
            0,
            $messages,
            'Filtered list must expose the first tuple slot.',
        );

        $first = $messages[0];

        self::assertIsArray(
            $first,
            'Each entry must be a tagged log tuple.',
        );
        self::assertArrayHasKey(
            0,
            $first,
            "Tagged log tuple must expose the 'value' slot.",
        );
        self::assertIsString(
            $first[0],
            'Throwable first element must be cast to its string form.',
        );
        self::assertStringContainsString(
            'boom',
            $first[0],
            'Stringified throwable must retain its message text.',
        );
    }

    public function testGetNameDefaultsToEmptyString(): void
    {
        self::assertSame(
            '',
            $this->createPanel()->getName(),
            'Base Panel exposes no display name.',
        );
    }

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

    public function testGetTraceLineDumpsValueWhenTraceLineClosureReturnsNonString(): void
    {
        [$panel, $module] = $this->createPanelWithModule();

        $this->setInaccessibleProperty(
            $module,
            'traceLine',
            static fn(): array => ['not' => 'string'],
        );

        $line = $panel->getTraceLine(['file' => 'file.php', 'line' => 10]);

        self::assertStringContainsString(
            "'not'",
            $line,
            'Non-string closure return must fall back to a VarDumper representation.',
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

    public function testGetTraceLineSkipsTraceMappingsWithNonScalarValues(): void
    {
        [$panel, $module] = $this->createPanelWithModule();

        $this->setInaccessibleProperty(
            $module,
            'tracePathMappings',
            ['/app' => ['ignored', 'array']],
        );

        $line = $panel->getTraceLine(['file' => '/app/file.php', 'line' => 10]);

        self::assertStringContainsString(
            'file:///app/file.php',
            $line,
            'Mapping values that are not scalar must be skipped, leaving the original path intact.',
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

    public function testSaveDefaultsToNull(): void
    {
        self::assertNull(
            $this->createPanel()->save(),
            'Base Panel records nothing by default.',
        );
    }

    public function testSetErrorMakesGetErrorAndHasErrorSurfaceTheFlattenedException(): void
    {
        $panel = $this->createPanel();

        $panel->setError(new FlattenException(new Exception('captured')));

        self::assertTrue(
            $panel->hasError(),
            "Recording an exception must flip 'hasError' to `true`.",
        );
        self::assertInstanceOf(
            FlattenException::class,
            $panel->getError(),
            "'getError' must surface the recorded FlattenException.",
        );
    }

    public function testThrowInvalidConfigExceptionWhenLogTargetIsMissing(): void
    {
        $panel = $this->createPanel();

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'The debug module logTarget must be initialized',
        );

        $this->invoke($panel, 'getLogTarget');
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
