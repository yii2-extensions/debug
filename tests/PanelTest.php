<?php

declare(strict_types=1);

namespace yii\debug\tests;

use Exception;
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Routing\DebugUrlGeneratorInterface;
use PHPForge\Debug\Storage\{ExceptionSnapshot, HydrationException};
use PHPUnit\Framework\Attributes\Group;
use yii\debug\{Module, Panel};
use yii\debug\tests\support\stub\CustomPanel;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see Panel} covering trace-line rendering, the `getToolbarData` template flow, and the
 * `getToolbarIcon` extension hook.
 */
#[Group('panel')]
final class PanelTest extends TestCase
{
    public function testCaptureDefaultsToNull(): void
    {
        self::assertNull(
            $this->createPanel()->capture(),
            'Base Panel records nothing by default.',
        );
    }

    public function testGetDetailDefaultsToEmptyString(): void
    {
        self::assertSame(
            '',
            $this->createPanel()->getDetail(),
            'Base Panel exposes no detail view.',
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

    public function testGetToolbarDataReturnsEmptyArrayWhenItemsAreEmpty(): void
    {
        $panel = $this->makeCustomPanel('quiet');

        $panel->stubName = 'Quiet';

        self::assertSame(
            [],
            $panel->getToolbarData(),
            'An empty items list must hide the chip entirely.',
        );
    }

    public function testGetToolbarDataWrapsStructuredItems(): void
    {
        $panel = $this->makeCustomPanel('custom');

        $panel->stubName = 'Custom';
        $panel->stubItems = [['value' => 42]];

        $data = $panel->getToolbarData();

        self::assertSame(
            'Custom',
            $data['title'] ?? null,
            'Title should mirror the panel name.',
        );
        self::assertSame(
            [['value' => 42]],
            $data['items'] ?? null,
            'Items must round-trip into the envelope verbatim.',
        );
        self::assertIsString(
            $data['url'] ?? null,
            'URL value must be a string.',
        );
        self::assertStringContainsString(
            'panel=custom',
            $data['url'],
            'URL should target the panel by id.',
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

    public function testRenderContextIsAnAdditivePortablePanelContract(): void
    {
        $panel = $this->createPanel();
        $context = new PanelRenderContext(
            tag: 'test-tag',
            panel: 'request',
            queryParams: [],
            theme: 'dark',
            urls: new class implements DebugUrlGeneratorInterface {
                public function action(string $action, string $tag, array $queryParams = []): string
                {
                    return "/debug/{$action}?tag={$tag}";
                }

                public function history(array $queryParams = []): string
                {
                    return '/debug/index';
                }

                public function panel(string $tag, string $panel, array $queryParams = []): string
                {
                    return "/debug/view?tag={$tag}&panel={$panel}";
                }
            },
        );

        self::assertNull(
            $panel->getRenderContext(),
            'Panels created outside a debugger detail request must remain usable without a render context.',
        );

        $panel->setRenderContext($context);

        self::assertSame(
            $context,
            $panel->getRenderContext(),
            'Debugger actions must be able to expose the portable render context without changing getDetail().',
        );
    }

    public function testSetErrorMakesGetErrorAndHasErrorSurfaceTheExceptionSnapshot(): void
    {
        $panel = $this->createPanel();

        $panel->setError(ExceptionSnapshot::fromThrowable(new Exception('captured')));

        self::assertTrue(
            $panel->hasError(),
            'Recording an exception must mark the panel as failed.',
        );
        self::assertInstanceOf(
            ExceptionSnapshot::class,
            $panel->getError(),
            'Recorded exception snapshot must be returned.',
        );
    }

    public function testThrowHydrationExceptionWhenTheBasePanelReceivesAPayload(): void
    {
        $panel = $this->createPanel();
        $panel->id = 'custom';

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage("Invalid debug snapshot value at '\$.panels.custom'");

        $panel->hydrate(['anything' => 1]);
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
