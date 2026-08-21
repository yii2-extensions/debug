<?php

declare(strict_types=1);

namespace yii\debug\tests\timeline;

use PHPForge\Debug\Panel\Log\LogSnapshot;
use PHPForge\Debug\Panel\MemorySample;
use PHPForge\Debug\Panel\Profile\ProfilingSnapshot;
use PHPForge\Debug\Panel\Timeline\TimelineSnapshot;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use ReflectionMethod;
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
    public function testAddPointsCalculatesExactCoordinates(): void
    {
        $panel = $this->makeTimelinePanel();

        $svg = new Svg($panel, ['x' => 200, 'y' => 50]);

        $this->setInaccessibleProperty($panel, 'memory', 1_000);
        $this->setInaccessibleProperty($panel, 'duration', 100.0);
        $this->setInaccessibleProperty($panel, 'start', 1_700_000_000_000.0);

        $count = $this->invoke(
            $svg,
            'addPoints',
            [
                [
                    new MemorySample(1_700_000_000_010.0, 100),
                    new MemorySample(1_700_000_000_020.0, 200),
                ],
            ],
        );

        self::assertSame(
            2,
            $count,
            'Two points must be appended to the trace.',
        );
        self::assertSame(
            [[20.0, 45.0], [40.0, 40.0]],
            $this->getInaccessibleProperty($svg, 'points'),
            'Points must be correctly calculated and stored.',
        );
    }

    public function testAddPointsKeepsExistingOrderWhenNoSamplesAreAdded(): void
    {
        $panel = $this->makeTimelinePanel();

        $svg = new Svg($panel);

        $points = [[500.0, 20.0], [100.0, 30.0]];

        $this->setInaccessibleProperty($svg, 'points', $points);

        self::assertSame(
            0,
            $this->invoke($svg, 'addPoints', [[]]),
            'No points must be appended when the input is empty.',
        );
        self::assertSame(
            $points,
            $this->getInaccessibleProperty($svg, 'points'),
            'Existing points must be preserved when no new points are added.',
        );
    }
    public function testAddPointsRemainsProtected(): void
    {
        self::assertTrue(
            (new ReflectionMethod(Svg::class, 'addPoints'))->isProtected(),
            'Must remain protected to avoid accidental misuse.',
        );
    }

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

    public function testAddPointsReturnsZeroWhenWidthIsZero(): void
    {
        $panel = $this->makeTimelinePanel();
        $svg = new Svg($panel, ['x' => 0]);

        self::assertSame(
            0,
            $this->invoke($svg, 'addPoints', [[new MemorySample(1_700_000_000_010.0, 1_024)]]),
            'Zero width must short-circuit.',
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

        $this->hydratePanel(
            $profilingPanel,
            ProfilingSnapshot::capture(
                0,
                0.0,
                [
                    ['t1', Logger::LEVEL_PROFILE_BEGIN, 'app\\db', 1_700_000_000.010, [], 1_048_576],
                    ['t1', Logger::LEVEL_PROFILE_END, 'app\\db', 1_700_000_000.020, [], 2_097_152],
                ],
            ),
        );

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

        $this->hydratePanel(
            $logPanel,
            LogSnapshot::capture([]),
        );

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

        self::assertSame(
            <<<'HTML'
            <svg width="1920" height="40" preserveAspectRatio="none" viewBox="0 0 1920 40" xmlns="http://www.w3.org/2000/svg">
            <defs>
            <linearGradient id="yii-debug-tl-memory-gradient" x1="0" x2="0" y1="1" y2="0">
            <stop offset="10%" stop-color="currentColor" stop-opacity="0.18"><stop offset="60%" stop-color="currentColor" stop-opacity="0.45"><stop offset="90%" stop-color="currentColor" stop-opacity="0.65"><stop offset="100%" stop-color="currentColor" stop-opacity="0.85">
            </linearGradient>
            </defs><g>
            <polygon points="0 40 0 30 100 20 1919.999 20 1920 40" fill="url(#yii-debug-tl-memory-gradient)"><polyline points="0 40 0 30 100 20 1920 20" fill="none" stroke="currentColor" stroke-width="1.5">
            </g>
            </svg>
            HTML,
            $markup,
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

        self::assertSame(
            <<<'HTML'
            <svg width="1920" height="40" preserveAspectRatio="none" viewBox="0 0 1920 40" xmlns="http://www.w3.org/2000/svg">
            <defs>
            <linearGradient id="yii-debug-tl-memory-gradient" x1="0" x2="0" y1="1" y2="0">
            <stop offset="50%" stop-color="#ff0000">
            </linearGradient>
            </defs><g>
            <polygon points="0 40 0 30 100 20 1919.999 20 1920 40" fill="url(#yii-debug-tl-memory-gradient)"><polyline points="0 40 0 30 100 20 1920 20" fill="none" stroke="currentColor" stroke-width="1.5">
            </g>
            </svg>
            HTML,
            (string) $svg,
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
