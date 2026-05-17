<?php

declare(strict_types=1);

namespace yii\debug\tests\timeline;

use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use RuntimeException;
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
 * `__toString` empty short-circuit, the points appended from valid log messages, the panel-clear `RuntimeException`,
 * and the early returns from `addPoints` when the panel memory or duration is non-positive.
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
                    ['t', Logger::LEVEL_PROFILE_BEGIN, 'c', 1_700_000_000.080, [], 2_097_152],
                    ['t', Logger::LEVEL_PROFILE_END, 'c', 1_700_000_000.010, [], 1_048_576],
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

        $profilingPanel->data = [
            'messages' => [
                ['t1', Logger::LEVEL_PROFILE_BEGIN, 'app\\db', 1_700_000_000.010, [], 1_048_576],
                ['t1', Logger::LEVEL_PROFILE_END, 'app\\db', 1_700_000_000.020, [], 2_097_152],
            ],
        ];

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

        $logPanel->data = ['no-messages-key' => 'value'];

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

        $profilingPanel->data = [
            'messages' => [
                ['t1', Logger::LEVEL_PROFILE_BEGIN, 'app\\db', 1_700_000_000.010, [], 1_048_576],
                'not-an-array',
            ],
        ];

        $svg = new Svg($panel);

        self::assertTrue(
            $svg->hasPoints(),
            'First valid message must surface before the loop breaks on the malformed entry.',
        );
    }

    public function testThrowRuntimeExceptionWhenPanelClearedAfterConstruction(): void
    {
        $panel = $this->makeTimelinePanel();

        $svg = new Svg($panel);

        $this->setInaccessibleProperty(
            $svg,
            'panel',
            null,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'TimelinePanel has not been set on the SVG renderer.',
        );

        $this->invoke($svg, 'addPoints', [[]]);
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

        $panel->load(['start' => 1_700_000_000.0, 'end' => 1_700_000_000.1, 'memory' => 2_097_152]);

        return $panel;
    }
}
