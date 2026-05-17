<?php

declare(strict_types=1);

namespace yii\debug\tests\timeline;

use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use stdClass;
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\{LogTarget, Module};
use yii\debug\models\timeline\Svg;
use yii\debug\panels\{ProfilingPanel, TimelinePanel};
use yii\debug\tests\support\TestCase;
use yii\log\Logger;
use yii\web\Controller;

/**
 * Unit tests for {@see TimelinePanel} covering the snapshot capture, the strict `load()` validation, the SVG renderer
 * lazy factory, the color-threshold normalization, the cached span rows, and the toolbar metadata.
 */
#[Group('panel')]
#[Group('timeline')]
final class TimelinePanelTest extends TestCase
{
    public function testGetColorsReturnsDefaultsSortedDescending(): void
    {
        $panel = $this->makeTimelinePanel();

        self::assertSame(
            [1 => '#8cc665', 10 => '#44a340', 20 => '#1e6823'],
            $panel->getColors(),
            'Default colors must keep the original insertion order.',
        );
    }

    public function testGetDetailRendersWithProfilingMessages(): void
    {
        $panel = $this->makeTimelinePanel();

        $start = 1_700_000_000.0;

        $this->primeProfilingPanel(
            $panel,
            ['time' => 0.1, 'messages' => []],
        );

        $panel->load(['start' => $start, 'end' => $start + 0.1, 'memory' => 1024]);


        self::assertNotEmpty(
            $panel->getDetail(),
            'Detail view must produce markup.',
        );
    }

    public function testGetDurationStartAndMemoryExposeLoadedValues(): void
    {
        $panel = $this->makeTimelinePanel();

        $start = 1_700_000_000.0;

        $panel->load(['start' => $start, 'end' => $start + 0.5, 'memory' => 2048]);

        self::assertEqualsWithDelta(
            500.0,
            $panel->getDuration(),
            1e-9,
            "Duration must echo '(end - start) * 1000' when profiling is absent.",
        );
        self::assertEqualsWithDelta(
            $start * 1000,
            $panel->getStart(),
            1e-3,
            'Start must echo the loaded value scaled to milliseconds.',
        );
        self::assertSame(
            2048,
            $panel->getMemory(),
            'Memory must echo the loaded peak value verbatim.',
        );
    }

    public function testGetModelsBuildsTypedSpansFromProfileBeginEndPair(): void
    {
        $panel = $this->makeTimelinePanel();

        $this->primeProfilingPanel(
            $panel,
            [
                'time' => 0.1,
                'messages' => [
                    ['token', Logger::LEVEL_PROFILE_BEGIN, 'app\\db', 1_700_000_000.0, [], 1024],
                    ['token', Logger::LEVEL_PROFILE_END, 'app\\db', 1_700_000_000.05, [], 2048],
                ],
            ],
        );

        $panel->load(['start' => 1_700_000_000.0, 'end' => 1_700_000_000.1, 'memory' => 1024]);

        $models = $panel->getModels();

        self::assertCount(
            1,
            $models,
            'Begin/End pair must produce one span row.',
        );

        $row = $models[0] ?? self::fail('Expected one span row.');

        self::assertSame(
            'app\\db',
            $row['category'] ?? null,
            'Category must round-trip.',
        );
        self::assertEqualsWithDelta(
            0.05,
            $row['duration'] ?? null,
            1e-3,
            'Duration must reflect the end-begin delta in seconds.',
        );
    }

    public function testGetModelsCachesRowsAndRebuildsOnRefresh(): void
    {
        $panel = $this->makeTimelinePanel();

        $this->primeProfilingPanel(
            $panel,
            [],
        );

        $panel->load(['start' => 1_700_000_000.0, 'end' => 1_700_000_000.1, 'memory' => 1024]);

        $first = $panel->getModels();
        $second = $panel->getModels();

        self::assertSame(
            $first,
            $second,
            'Second call must return the cached row list.',
        );

        self::assertSame(
            [],
            $panel->getModels(true),
            "Refresh with no profiling messages must yield '[]'.",
        );
    }

    public function testGetModelsFiltersNonArrayProfilingMessages(): void
    {
        $panel = $this->makeTimelinePanel();

        $this->primeProfilingPanel(
            $panel,
            [
                'time' => 0.1,
                'messages' => [
                    'drop-string-entry',
                    42,
                    ['token', Logger::LEVEL_PROFILE_BEGIN, 'app\\db', 1_700_000_000.0, [], 1024],
                    ['token', Logger::LEVEL_PROFILE_END, 'app\\db', 1_700_000_000.05, [], 2048],
                ],
            ],
        );

        $panel->load(['start' => 1_700_000_000.0, 'end' => 1_700_000_000.1, 'memory' => 1024]);

        self::assertCount(
            1,
            $panel->getModels(),
            'Non-array message entries must be dropped before the timing pass.',
        );
    }

    public function testGetModelsReturnsEmptyWhenProfilingMessagesArray(): void
    {
        $panel = $this->makeTimelinePanel();

        $this->primeProfilingPanel(
            $panel,
            [],
        );

        $panel->load(['start' => 1_700_000_000.0, 'end' => 1_700_000_000.1, 'memory' => 1024]);

        self::assertSame(
            [],
            $panel->getModels(),
            'No profiling messages means no span rows.',
        );
    }

    public function testGetModelsReturnsEmptyWhenProfilingPanelDataIsNull(): void
    {
        $panel = $this->makeTimelinePanel();

        $panel->load(['start' => 1_700_000_000.0, 'end' => 1_700_000_000.1, 'memory' => 1024]);

        self::assertSame(
            [],
            $panel->getModels(),
            'Profiling panel without saved data must yield no span rows.',
        );
    }

    public function testGetNameAndIcon(): void
    {
        $panel = $this->makeTimelinePanel();

        self::assertSame(
            'Timeline',
            $panel->getName(),
            "Display name must be 'Timeline'.",
        );
        self::assertSame(
            'timeline',
            $panel->getToolbarIcon(),
            "Icon key must be 'timeline'.",
        );
    }

    public function testGetSvgInstantiatesLazilyAndMemoizes(): void
    {
        $panel = $this->makeTimelinePanel();

        $first = $panel->getSvg();
        $second = $panel->getSvg();

        self::assertSame(
            $first,
            $second,
            'Second call must return the memoized instance.',
        );
    }

    public function testGetSvgOptionsReturnsDefaults(): void
    {
        $panel = $this->makeTimelinePanel();

        self::assertSame(
            ['class' => Svg::class],
            $panel->getSvgOptions(),
            'Defaults must carry the Svg class entry only.',
        );
    }

    public function testLoadFallsBackToEndMinusStartWhenProfilingTimeMissing(): void
    {
        $panel = $this->makeTimelinePanel();

        $this->primeProfilingPanel(
            $panel,
            ['messages' => []],
        );

        $start = 1_700_000_000.0;

        $panel->load(['start' => $start, 'end' => $start + 0.25, 'memory' => 1024]);

        self::assertEqualsWithDelta(
            250.0,
            $panel->getDuration(),
            1e-9,
            "Missing profiling time must fall back to '(end - start) * 1000'.",
        );
    }

    public function testLoadUsesProfilingTimeWhenAvailable(): void
    {
        $panel = $this->makeTimelinePanel();

        $this->primeProfilingPanel(
            $panel,
            ['time' => 0.5, 'messages' => []],
        );

        $start = 1_700_000_000.0;

        $panel->load(['start' => $start, 'end' => $start + 0.1, 'memory' => 1024]);

        self::assertEqualsWithDelta(
            500.0,
            $panel->getDuration(),
            1e-9,
            'Profiling time must override the start/end delta.',
        );
    }

    public function testNormalizeMessagesReturnsEmptyArrayForNonArrayInput(): void
    {
        $panel = $this->makeTimelinePanel();

        self::assertSame(
            [],
            $this->invoke(
                $panel,
                'normalizeMessages',
                ['not-an-array'],
            ),
            "Non-array input must collapse to '[]'.",
        );
    }

    public function testNormalizeTimingReturnsNullForNonArrayInput(): void
    {
        $panel = $this->makeTimelinePanel();

        self::assertNull(
            $this->invoke(
                $panel,
                'normalizeTiming',
                ['not-an-array'],
            ),
            "Non-array timing must collapse to 'null'.",
        );
    }

    public function testNormalizeTimingReturnsNullWhenTimestampOrDurationMissing(): void
    {
        $panel = $this->makeTimelinePanel();

        self::assertNull(
            $this->invoke(
                $panel,
                'normalizeTiming',
                [['duration' => 1.0]],
            ),
            "Missing timestamp must yield 'null'.",
        );
        self::assertNull(
            $this->invoke(
                $panel,
                'normalizeTiming',
                [['timestamp' => 1.0]],
            ),
            "Missing duration must yield 'null'.",
        );
    }

    public function testSaveCapturesStartEndAndMemory(): void
    {
        $panel = $this->makeTimelinePanel();

        $_SERVER['REQUEST_TIME_FLOAT'] = 1_700_000_000.0;

        $saved = $panel->save();

        self::assertEqualsWithDelta(
            1_700_000_000.0,
            $saved['start'],
            1e-3,
            'Start must echo the request time.',
        );
        self::assertGreaterThanOrEqual(
            $saved['start'],
            $saved['end'],
            'End must be greater than or equal to start.',
        );
        self::assertGreaterThan(
            0,
            $saved['memory'],
            'Memory peak must be positive.',
        );
    }

    public function testSaveFallsBackToMicrotimeWhenRequestTimeFloatMissing(): void
    {
        $panel = $this->makeTimelinePanel();

        unset($_SERVER['REQUEST_TIME_FLOAT']);

        $before = microtime(true);

        $saved = $panel->save();

        $after = microtime(true);

        self::assertGreaterThanOrEqual(
            $before,
            $saved['start'],
            "Start must fall back to 'microtime(true)'.",
        );
        self::assertLessThanOrEqual(
            $after,
            $saved['start'],
            'Start must not jump past the call site.',
        );
    }

    public function testSetColorsFiltersNonStringEntriesAndSortsDescending(): void
    {
        $panel = $this->makeTimelinePanel();

        $panel->setColors([5 => '#aaa', 50 => '#bbb', 25 => 42, 75 => '#ccc']);

        self::assertSame(
            [75 => '#ccc', 50 => '#bbb', 5 => '#aaa'],
            $panel->getColors(),
            'Non-string values must be dropped; remaining keys must sort descending.',
        );
    }

    public function testSetSvgOptionsMergesAndResetsMemoizedRenderer(): void
    {
        $panel = $this->makeTimelinePanel();

        $first = $panel->getSvg();

        $panel->setSvgOptions(['stroke' => '#ff0000']);

        $second = $panel->getSvg();

        self::assertNotSame(
            $first,
            $second,
            'Memoized renderer must be discarded after a setter call.',
        );
        self::assertSame(
            '#ff0000',
            $second->stroke,
            'Overridden option must reach the rebuilt renderer.',
        );
        self::assertSame(
            ['class' => Svg::class, 'stroke' => '#ff0000'],
            $panel->getSvgOptions(),
            'Options must be merged on top of the defaults.',
        );
    }

    public function testThrowInvalidConfigExceptionWhenProfilingPanelIsMissing(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug');
        $module->logTarget = new LogTarget($module);

        unset($module->panels['profiling']);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'Unable to determine the profiling panel',
        );

        new TimelinePanel(['id' => 'timeline', 'module' => $module]);
    }

    public function testThrowInvalidConfigExceptionWhenSvgClassDoesNotExtendSvg(): void
    {
        $panel = $this->makeTimelinePanel();

        $panel->setSvgOptions(['class' => stdClass::class]);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'Timeline SVG class must extend ',
        );

        $panel->getSvg();
    }

    public function testThrowInvalidConfigExceptionWhenSvgFactoryReturnsNonSvg(): void
    {
        $panel = $this->makeTimelinePanel();

        Yii::$container->set(Svg::class, static fn(): stdClass => new stdClass());

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'Timeline SVG factory must create ',
        );

        $panel->getSvg();
    }

    public function testThrowRuntimeExceptionWhenLoadDataIsNotArray(): void
    {
        $panel = $this->makeTimelinePanel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Unable to load timeline data',
        );

        $panel->load('not-an-array');
    }

    public function testThrowRuntimeExceptionWhenLoadDurationIsZero(): void
    {
        $panel = $this->makeTimelinePanel();

        $start = 1_700_000_000.0;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Duration cannot be zero',
        );

        $panel->load(['start' => $start, 'end' => $start, 'memory' => 1024]);
    }

    public function testThrowRuntimeExceptionWhenLoadEndIsMissing(): void
    {
        $panel = $this->makeTimelinePanel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Unable to determine request end time',
        );

        $panel->load(['start' => 1_700_000_000.0, 'memory' => 1024]);
    }

    public function testThrowRuntimeExceptionWhenLoadMemoryIsMissing(): void
    {
        $panel = $this->makeTimelinePanel();

        $start = 1_700_000_000.0;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Unable to determine used memory in request',
        );

        $panel->load(['start' => $start, 'end' => $start + 0.1]);
    }

    public function testThrowRuntimeExceptionWhenLoadMemoryIsNonPositive(): void
    {
        $panel = $this->makeTimelinePanel();

        $start = 1_700_000_000.0;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Unable to determine used memory in request',
        );

        $panel->load(['start' => $start, 'end' => $start + 0.1, 'memory' => 0]);
    }

    public function testThrowRuntimeExceptionWhenLoadStartIsMissing(): void
    {
        $panel = $this->makeTimelinePanel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Unable to determine request start time',
        );

        $panel->load(['end' => 1_700_000_000.1, 'memory' => 1024]);
    }

    public function testThrowRuntimeExceptionWhenLoadStartIsNonPositive(): void
    {
        $panel = $this->makeTimelinePanel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Unable to determine request start time',
        );

        $panel->load(['start' => 0, 'end' => 1.0, 'memory' => 1024]);
    }

    /**
     * Builds a wired {@see TimelinePanel} with the parent module carrying a registered {@see ProfilingPanel}, so
     * {@see TimelinePanel::init()} succeeds without a full module bootstrap.
     */
    private function makeTimelinePanel(): TimelinePanel
    {
        $assetPath = dirname(__DIR__, 2) . '/runtime/assets';

        @mkdir($assetPath, 0o777, true);

        $this->mockWebApplication(
            [
                'components' => [
                    'assetManager' => [
                        'basePath' => $assetPath,
                        'baseUrl' => '/assets',
                    ],
                ],
            ],
        );

        $module = new Module('debug');
        $module->logTarget = new LogTarget($module);

        Yii::$app->controller = new Controller('debug', $module);

        $profiling = new ProfilingPanel(['id' => 'profiling', 'module' => $module]);

        $module->panels['profiling'] = $profiling;

        return new TimelinePanel(['id' => 'timeline', 'module' => $module]);
    }

    /**
     * Sets the {@see ProfilingPanel::$data} payload backing the profiling lookups (`time` for duration override,
     * `messages` for the span rows).
     *
     * @param array<string, mixed> $data Profiling payload to inject.
     */
    private function primeProfilingPanel(TimelinePanel $panel, array $data): void
    {
        $module = $panel->module ?? self::fail('Module must be wired.');

        $profiling = $module->panels['profiling'] ?? null;

        self::assertInstanceOf(
            ProfilingPanel::class,
            $profiling,
            'Profiling panel must be wired.',
        );

        $profiling->data = $data;
    }
}
