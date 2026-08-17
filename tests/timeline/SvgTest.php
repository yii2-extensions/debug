<?php

declare(strict_types=1);

namespace yii\debug\tests\timeline;

use PHPForge\Debug\Panel\Log\LogSnapshot;
use PHPForge\Debug\Panel\MemorySample;
use PHPForge\Debug\Panel\Profile\ProfilingSnapshot;
use PHPForge\Debug\Panel\Timeline\TimelineSnapshot;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use Yii;
use yii\debug\{LogTarget, Module};
use yii\debug\models\timeline\Svg;
use yii\debug\panels\{LogPanel, ProfilingPanel, TimelinePanel};
use yii\debug\tests\support\TestCase;
use yii\log\Logger;
use yii\web\Controller;

use function count;

/**
 * Unit tests for {@see Svg} covering the constructor branches (module-less / source-panel-less / invalid-messages),
 * `__toString` empty short-circuit, the `currentColor` stroke and gradient-stop defaults, the points appended from
 * valid log messages, and the early returns from `addPoints` when the panel memory or duration is non-positive.
 */
#[Group('timeline')]
final class SvgTest extends TestCase
{
    public function testAddPointsReturnsZeroWhenComputedXOneIsNonPositive(): void
    {
        $panel = $this->makeTimelinePanel();

        $svg = new Svg($panel);

        $this->setInaccessibleProperty(
            $panel,
            'duration',
            0.0,
        );

        $appended = $this->invoke(
            $svg,
            'addPoints',
            [[['t', Logger::LEVEL_PROFILE_BEGIN, 'c', 1_700_000_000.0, [], 1024]]],
        );

        self::assertSame(
            0,
            $appended,
            "Zero duration must short-circuit on the 'xOne <= 0' guard.",
        );
    }

    public function testAddPointsReturnsZeroWhenPanelMemoryIsNonPositive(): void
    {
        $panel = $this->makeTimelinePanel();

        $svg = new Svg($panel);

        $this->setInaccessibleProperty(
            $panel,
            'memory',
            0,
        );

        $appended = $this->invoke(
            $svg,
            'addPoints',
            [[['t', Logger::LEVEL_PROFILE_BEGIN, 'c', 1_700_000_000.0, [], 1024]]],
        );

        self::assertSame(
            0,
            $appended,
            'Non-positive memory must short-circuit.',
        );
    }

    public function testAddPointsSortsMergedPointsByXWhenAppendingToExistingTrace(): void
    {
        $panel = $this->makeTimelinePanel();

        $svg = new Svg($panel);

        $this->setInaccessibleProperty(
            $svg,
            'points',
            [[500.0, 20.0]],
        );

        $this->invoke(
            $svg,
            'addPoints',
            [
                [
                    new MemorySample(1_700_000_000_080.0, 2_097_152),
                    new MemorySample(1_700_000_000_010.0, 1_048_576),
                ],
            ],
        );

        $points = $this->getInaccessibleProperty($svg, 'points');

        self::assertIsArray(
            $points,
            'Points must remain a list.',
        );
        self::assertGreaterThanOrEqual(
            2,
            count($points),
            'New points must be appended to the existing trace.',
        );

        $xs = [];

        foreach ($points as $point) {
            self::assertIsArray(
                $point,
                "Each plotted point must be an ['x', 'y'] pair.",
            );

            $xs[] = $point[0] ?? 0.0;
        }

        $sorted = $xs;

        sort($sorted);

        self::assertSame(
            $sorted,
            $xs,
            "Merging into an existing trace must sort the combined point list by 'x'.",
        );
    }

    public function testConstructorAppendsPointsFromValidMessages(): void
    {
        $panel = $this->makeTimelinePanel();

        $profilingPanel = $panel->module->panels['profiling'] ?? null;

        self::assertInstanceOf(
            ProfilingPanel::class,
            $profilingPanel,
            'Profiling panel must be wired.',
        );

        $this->hydratePanel($profilingPanel, ProfilingSnapshot::capture(0, 0.0, [
            ['t1', Logger::LEVEL_PROFILE_BEGIN, 'app\\db', 1_700_000_000.010, [], 1_048_576],
            ['t1', Logger::LEVEL_PROFILE_END, 'app\\db', 1_700_000_000.020, [], 2_097_152],
        ]));

        $svg = new Svg($panel);

        self::assertTrue(
            $svg->hasPoints(),
            'Valid messages with timestamp + memory entries must produce plotted points.',
        );
    }

    public function testConstructorShortCircuitsWhenPanelModuleIsNull(): void
    {
        $this->mockWebApplication();

        $panel = (new ReflectionClass(TimelinePanel::class))->newInstanceWithoutConstructor();

        $svg = new Svg($panel);

        self::assertFalse(
            $svg->hasPoints(),
            'Module-less panel must short-circuit the constructor.',
        );
    }

    public function testConstructorSkipsSourcePanelsWithoutMessages(): void
    {
        $panel = $this->makeTimelinePanel();

        $logPanel = $panel->module->panels['log'] ?? null;

        self::assertInstanceOf(LogPanel::class, $logPanel, 'Log panel must be wired.');

        $this->hydratePanel($logPanel, LogSnapshot::capture([]));

        $svg = new Svg($panel);

        self::assertFalse(
            $svg->hasPoints(),
            "Source panels whose data lacks 'messages' must be skipped.",
        );
    }

    public function testConstructorSkipsUnregisteredSourcePanels(): void
    {
        $panel = $this->makeTimelinePanel();

        self::assertNotNull(
            $panel->module,
            "Module must be wired by 'makeTimelinePanel()'.",
        );

        // Module is wired but neither 'log' nor 'profiling' has any source data registered.
        unset($panel->module->panels['log'], $panel->module->panels['profiling']);

        $svg = new Svg($panel);

        self::assertFalse(
            $svg->hasPoints(),
            "Unregistered source panels must be skipped via the defensive 'continue'.",
        );
    }

    public function testConstructorStopsAtMalformedMessageEntry(): void
    {
        $panel = $this->makeTimelinePanel();

        $profilingPanel = $panel->module->panels['profiling'] ?? null;

        self::assertInstanceOf(
            ProfilingPanel::class,
            $profilingPanel,
            'Profiling panel must be wired.',
        );

        $this->hydratePanel($profilingPanel, ProfilingSnapshot::capture(0, 0.0, [
            ['t1', Logger::LEVEL_PROFILE_BEGIN, 'app\\db', 1_700_000_000.010, [], 1_048_576],
            'not-an-array',
        ]));

        $svg = new Svg($panel);

        self::assertTrue(
            $svg->hasPoints(),
            'First valid message must surface before the loop breaks on the malformed entry.',
        );
    }

    public function testToStringEmitsCurrentColorOpacityStopsByDefault(): void
    {
        $panel = $this->makeTimelinePanel();

        $svg = new Svg($panel);

        $this->setInaccessibleProperty(
            $svg,
            'points',
            [[0.0, 30.0], [100.0, 20.0]],
        );

        $markup = (string) $svg;

        self::assertStringContainsString(
            'stop-color="currentColor"',
            $markup,
            'Stops must paint with the inherited color.',
        );
        self::assertStringContainsString(
            'stop-opacity=',
            $markup,
            'Stops must fade via `stop-opacity`.',
        );
    }

    public function testToStringEmitsPolygonAndPolylineWhenPointsExist(): void
    {
        $panel = $this->makeTimelinePanel();

        $svg = new Svg($panel);

        $this->setInaccessibleProperty(
            $svg,
            'points',
            [[0.0, 30.0], [100.0, 20.0]],
        );

        $markup = (string) $svg;

        self::assertStringContainsString(
            '<svg',
            $markup,
            'SVG must wrap the chart.',
        );
        self::assertStringContainsString(
            '<polygon',
            $markup,
            'Polygon (gradient area) must be emitted.',
        );
        self::assertStringContainsString(
            '<polyline',
            $markup,
            'Polyline (stroke trace) must be emitted.',
        );
        self::assertStringContainsString(
            'linearGradient',
            $markup,
            'Linear gradient must be defined.',
        );
    }

    public function testToStringKeepsHexStopColorWhenGradientValueIsString(): void
    {
        $panel = $this->makeTimelinePanel();

        $svg = new Svg($panel);

        $svg->gradient = [50 => '#ff0000'];

        $this->setInaccessibleProperty(
            $svg,
            'points',
            [[0.0, 30.0], [100.0, 20.0]],
        );

        $markup = (string) $svg;

        self::assertStringContainsString(
            'stop-color="#ff0000"',
            $markup,
            'Configured hex stop must be emitted verbatim.',
        );
        self::assertStringNotContainsString(
            'stop-opacity',
            $markup,
            'Hex stops must not emit an opacity.',
        );
    }

    public function testToStringReturnsEmptyWhenNoPointsPlotted(): void
    {
        $panel = $this->makeTimelinePanel();

        $svg = new Svg($panel);

        self::assertSame(
            '',
            (string) $svg,
            'Empty point list must collapse the SVG to an empty string.',
        );
    }

    public function testToStringScopesGradientIdToMemoryChart(): void
    {
        $panel = $this->makeTimelinePanel();

        $svg = new Svg($panel);

        $this->setInaccessibleProperty(
            $svg,
            'points',
            [[0.0, 30.0], [100.0, 20.0]],
        );

        $markup = (string) $svg;

        self::assertStringContainsString(
            'id="yii-debug-tl-memory-gradient"',
            $markup,
            'Gradient id must be namespaced.',
        );
        self::assertStringContainsString(
            'url(#yii-debug-tl-memory-gradient)',
            $markup,
            'Polygon fill must reference the namespaced gradient.',
        );
    }

    public function testToStringStrokesPolylineWithCurrentColorByDefault(): void
    {
        $panel = $this->makeTimelinePanel();

        $svg = new Svg($panel);

        $this->setInaccessibleProperty(
            $svg,
            'points',
            [[0.0, 30.0], [100.0, 20.0]],
        );

        self::assertStringContainsString(
            'stroke="currentColor"',
            (string) $svg,
            'Trace must inherit the CSS color.',
        );
    }

    private function makeTimelinePanel(): TimelinePanel
    {
        $this->mockWebApplication();

        $module = new Module('debug');
        $module->logTarget = new LogTarget($module);

        Yii::$app->controller = new Controller('debug', $module);

        $logPanel = new LogPanel(['id' => 'log', 'module' => $module]);
        $profilingPanel = new ProfilingPanel(['id' => 'profiling', 'module' => $module]);

        $module->panels = ['log' => $logPanel, 'profiling' => $profilingPanel];

        $panel = new TimelinePanel(['id' => 'timeline', 'module' => $module]);

        $this->hydratePanel($panel, new TimelineSnapshot(1_700_000_000.0, 1_700_000_000.1, 2_097_152));

        return $panel;
    }
}
