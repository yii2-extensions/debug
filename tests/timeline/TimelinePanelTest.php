<?php

declare(strict_types=1);

namespace yii\debug\tests\timeline;

use PHPForge\Debug\Panel\Profile\ProfilingSnapshot;
use PHPForge\Debug\Panel\Timeline\TimelineSnapshot;
use PHPForge\Debug\Storage\HydrationException;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use stdClass;
use Yii;
use yii\base\InvalidConfigException;
use yii\debug\exception\Message;
use yii\debug\{LogTarget, Module};
use yii\debug\models\timeline\Svg;
use yii\debug\panels\{ProfilingPanel, TimelinePanel};
use yii\debug\tests\support\TestCase;
use yii\log\Logger;
use yii\web\Controller;

use function array_key_exists;

/**
 * Unit tests for {@see TimelinePanel} covering the strict `hydrate()` validation, the SVG renderer lazy factory, the
 * cached span rows, and the toolbar metadata.
 *
 * @phpstan-import-type LogMessage from \PHPForge\Debug\Panel\Log\LogSnapshot
 */
#[Group('panel')]
#[Group('timeline')]
final class TimelinePanelTest extends TestCase
{
    public function testGetDetailRendersWithProfilingMessages(): void
    {
        $panel = $this->makeTimelinePanel();

        $start = 1_700_000_000.0;

        $this->primeProfilingPanel(
            $panel,
            ['time' => 0.1, 'messages' => []],
        );

        $this->hydratePanel(
            $panel,
            new TimelineSnapshot($start, $start + 0.1, 1024),
        );


        self::assertNotEmpty(
            $panel->getDetail(),
            'Detail view must produce markup.',
        );
    }

    public function testGetDurationStartAndMemoryExposeLoadedValues(): void
    {
        $panel = $this->makeTimelinePanel();

        $start = 1_700_000_000.0;

        $this->hydratePanel(
            $panel,
            new TimelineSnapshot($start, $start + 0.5, 2048),
        );

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

        $this->hydratePanel(
            $panel,
            new TimelineSnapshot(1_700_000_000.0, 1_700_000_000.1, 1024),
        );

        $models = $panel->getModels();

        self::assertCount(
            1,
            $models,
            'Begin/End pair must produce one span row.',
        );

        $row = $models[0];

        self::assertSame(
            'app\\db',
            $row->category,
            'Category must round-trip.',
        );
        self::assertEqualsWithDelta(
            50.0,
            $row->duration,
            1e-1,
            'Duration must reflect the end-begin delta in milliseconds.',
        );
    }

    public function testGetModelsCachesRowsAndRebuildsOnRefresh(): void
    {
        $panel = $this->makeTimelinePanel();

        $this->primeProfilingPanel(
            $panel,
            [],
        );

        $this->hydratePanel(
            $panel,
            new TimelineSnapshot(1_700_000_000.0, 1_700_000_000.1, 1024),
        );

        $first = $panel->getModels();
        $second = $panel->getModels();

        self::assertSame(
            $first,
            $second,
            'Second call must return the cached row list.',
        );
        self::assertSame(
            [],
            $panel->getModels(),
            "Refresh with no profiling messages must yield '[]'.",
        );
    }

    public function testGetModelsReturnsEmptyWhenProfilingMessagesArray(): void
    {
        $panel = $this->makeTimelinePanel();

        $this->primeProfilingPanel(
            $panel,
            [],
        );

        $this->hydratePanel(
            $panel,
            new TimelineSnapshot(1_700_000_000.0, 1_700_000_000.1, 1024),
        );

        self::assertSame(
            [],
            $panel->getModels(),
            'No profiling messages means no span rows.',
        );
    }

    public function testGetModelsReturnsEmptyWhenProfilingPanelDataIsNull(): void
    {
        $panel = $this->makeTimelinePanel();

        $this->hydratePanel(
            $panel,
            new TimelineSnapshot(1_700_000_000.0, 1_700_000_000.1, 1024),
        );

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

    public function testHydrateFallsBackToEndMinusStartWhenProfilingTimeMissing(): void
    {
        $panel = $this->makeTimelinePanel();

        $this->primeProfilingPanel(
            $panel,
            ['messages' => []],
        );

        $start = 1_700_000_000.0;

        $this->hydratePanel(
            $panel,
            new TimelineSnapshot($start, $start + 0.25, 1024),
        );

        self::assertEqualsWithDelta(
            250.0,
            $panel->getDuration(),
            1e-9,
            "Missing profiling time must fall back to '(end - start) * 1000'.",
        );
    }

    public function testHydrateUsesProfilingTimeWhenAvailable(): void
    {
        $panel = $this->makeTimelinePanel();

        $this->primeProfilingPanel(
            $panel,
            ['time' => 0.5, 'messages' => []],
        );

        $start = 1_700_000_000.0;

        $this->hydratePanel(
            $panel,
            new TimelineSnapshot($start, $start + 0.1, 1024),
        );

        self::assertEqualsWithDelta(
            500.0,
            $panel->getDuration(),
            1e-9,
            'Profiling time must override the start/end delta.',
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

    public function testThrowHydrationExceptionWhenLoadDataIsNotArray(): void
    {
        $panel = $this->makeTimelinePanel();

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            'expected an object',
        );

        TimelineSnapshot::fromArray('not-an-array', '$.panels.timeline');
    }

    public function testThrowHydrationExceptionWhenLoadEndIsMissing(): void
    {
        $panel = $this->makeTimelinePanel();

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            '$.panels.timeline.end',
        );

        $panel->hydrate(['start' => 1_700_000_000.0, 'memory' => 1024]);
    }

    public function testThrowHydrationExceptionWhenLoadMemoryIsMissing(): void
    {
        $panel = $this->makeTimelinePanel();

        $start = 1_700_000_000.0;

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            '$.panels.timeline.memory',
        );

        $panel->hydrate(['start' => $start, 'end' => $start + 0.1]);
    }

    public function testThrowHydrationExceptionWhenLoadStartIsMissing(): void
    {
        $panel = $this->makeTimelinePanel();

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            '$.panels.timeline.start',
        );

        $panel->hydrate(['end' => 1_700_000_000.1, 'memory' => 1024]);
    }

    public function testThrowInvalidConfigExceptionWhenProfilingPanelIsMissing(): void
    {
        $this->mockWebApplication();

        $module = new Module('debug');

        $module->logTarget = new LogTarget($module);

        unset($module->panels['profiling']);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::PROFILING_PANEL_UNAVAILABLE->getMessage(),
        );

        new TimelinePanel(['id' => 'timeline', 'module' => $module]);
    }

    public function testThrowInvalidConfigExceptionWhenSvgClassDoesNotExtendSvg(): void
    {
        $panel = $this->makeTimelinePanel();

        $panel->setSvgOptions(['class' => stdClass::class]);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::TIMELINE_SVG_CLASS_INVALID->getMessage(Svg::class),
        );

        $panel->getSvg();
    }

    public function testThrowInvalidConfigExceptionWhenSvgFactoryReturnsNonSvg(): void
    {
        $panel = $this->makeTimelinePanel();

        Yii::$container->set(Svg::class, static fn(): stdClass => new stdClass());

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            Message::TIMELINE_SVG_FACTORY_INVALID->getMessage(Svg::class),
        );

        $panel->getSvg();
    }

    public function testThrowRuntimeExceptionWhenLoadDurationIsZero(): void
    {
        $panel = $this->makeTimelinePanel();

        $start = 1_700_000_000.0;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            Message::TIMELINE_DURATION_ZERO->getMessage(),
        );

        $this->hydratePanel(
            $panel,
            new TimelineSnapshot($start, $start, 1024),
        );
    }

    public function testThrowRuntimeExceptionWhenLoadMemoryIsNonPositive(): void
    {
        $panel = $this->makeTimelinePanel();

        $start = 1_700_000_000.0;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            Message::REQUEST_MEMORY_UNAVAILABLE->getMessage(),
        );

        $this->hydratePanel(
            $panel,
            new TimelineSnapshot($start, $start + 0.1, 0),
        );
    }

    public function testThrowRuntimeExceptionWhenLoadStartIsNonPositive(): void
    {
        $panel = $this->makeTimelinePanel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            Message::REQUEST_START_TIME_UNAVAILABLE->getMessage(),
        );

        $this->hydratePanel(
            $panel,
            new TimelineSnapshot(0, 1.0, 1024),
        );
    }

    public function testThrowRuntimeExceptionWhenTheCapturedEndTimeIsNotPositive(): void
    {
        $panel = $this->makeTimelinePanel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            Message::REQUEST_END_TIME_UNAVAILABLE->getMessage(),
        );

        $panel->hydrate((new TimelineSnapshot(1_700_000_000.0, 0.0, 1024))->jsonSerialize());
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
     * Hydrates the profiling panel used for the duration override and span rows.
     *
     * @param array{time?: float, messages?: list<LogMessage>} $data Profiling payload to inject.
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

        if (!array_key_exists('time', $data)) {
            return;
        }

        $time = $data['time'];
        $messages = $data['messages'] ?? [];

        $this->hydratePanel(
            $profiling,
            ProfilingSnapshot::capture(0, $time, $messages),
        );
    }
}
