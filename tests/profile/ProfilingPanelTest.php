<?php

declare(strict_types=1);

namespace yii\debug\tests\profile;

use PHPForge\Debug\Helper\Format;
use PHPForge\Debug\Panel\Profile\ProfilingSnapshot;
use PHPForge\Debug\Storage\ExceptionSnapshot;
use PHPUnit\Framework\Attributes\Group;
use ReflectionMethod;
use RuntimeException;
use Yii;
use yii\debug\panels\ProfilingPanel;
use yii\debug\tests\support\stub\CapturingView;
use yii\debug\tests\support\TestCase;
use yii\helpers\Url;
use yii\log\Logger;

/**
 * Unit tests for {@see ProfilingPanel} covering the typed row decoration, the toolbar items (time + memory), the
 * title-blanking on the toolbar payload, and snapshot hydration.
 */
#[Group('panel')]
#[Group('profile')]
final class ProfilingPanelTest extends TestCase
{
    public function testGetMemoryUsageRemainsPublicAndDefaultsToZero(): void
    {
        self::assertTrue(
            (new ReflectionMethod(ProfilingPanel::class, 'getMemoryUsage'))->isPublic(),
            'Memory usage must remain a public API for the toolbar.',
        );

        $panel = $this->makePanel(
            ProfilingPanel::class,
        );

        self::assertSame(
            0,
            $panel->getMemoryUsage(),
            'Unhydrated memory usage must default to zero.',
        );
    }

    public function testGetDetailPassesExactMetricsAndTimelineUrlToView(): void
    {
        $panel = $this->makePanel(
            ProfilingPanel::class,
            ['view' => CapturingView::class],
        );

        $this->hydratePanel(
            $panel,
            ProfilingSnapshot::capture(1_234_567, 1.25, []),
        );

        self::assertSame(
            'rendered',
            $panel->getDetail(),
            'Detail view must be rendered.',
        );

        $view = Yii::$app->getView();

        self::assertInstanceOf(
            CapturingView::class,
            $view,
            'Detail view must be rendered through the capturing view.',
        );
        self::assertSame(
            'panels/profile/detail',
            $view->renderView,
            'Detail view must be rendered through the correct view file.',
        );
        self::assertSame(
            '1,250 ms',
            $view->renderParams['time'] ?? null,
            'Detail view must receive the exact time in milliseconds.',
        );
        self::assertSame(
            Format::bytesToMb(1_234_567, 3),
            $view->renderParams['memory'] ?? null,
            'Detail view must receive the exact memory in megabytes.',
        );
        self::assertSame(
            Url::to(['/debug/view', 'panel' => 'timeline', 'tag' => '']),
            $view->renderParams['timelineUrl'] ?? null,
            'Detail view must receive the correct timeline URL.',
        );
    }

    public function testUnhydratedDetailAndToolbarUseZeroFallbacks(): void
    {
        $panel = $this->makePanel(
            ProfilingPanel::class,
            ['view' => CapturingView::class],
        );

        $panel->module = null;

        self::assertSame(
            'rendered',
            $panel->getDetail(),
            'Detail view must be rendered even when unhydrated.',
        );

        $view = Yii::$app->getView();

        self::assertInstanceOf(
            CapturingView::class,
            $view,
            'Detail view must be rendered through the capturing view.',
        );
        self::assertSame(
            '0 ms',
            $view->renderParams['time'] ?? null,
            'Detail view must receive the exact time in milliseconds.',
        );
        self::assertSame(
            '#',
            $view->renderParams['timelineUrl'] ?? null,
            'Detail view must receive the correct timeline URL.',
        );
        self::assertSame(
            [
                ['title' => 'Total processing time', 'value' => '0 ms'],
                ['title' => 'Peak memory', 'value' => '0.000 MB'],
            ],
            $this->invoke($panel, 'getToolbarItems'),
            'Toolbar must receive zeroed metrics when unhydrated.',
        );
    }

    public function testCaptureScalesMemorySampleTimeToMilliseconds(): void
    {
        $snapshot = ProfilingSnapshot::capture(
            0,
            0.0,
            [['sample', Logger::LEVEL_INFO, 'application', 1.25, [], 2_048]],
        );

        $samples = $snapshot->samples();

        self::assertCount(
            1,
            $samples,
            'A logger tuple with time and memory must produce one sample.',
        );
        self::assertSame(
            1_250.0,
            $samples[0]->time,
            'Sample timestamps must be converted to milliseconds.',
        );
        self::assertSame(
            2_048,
            $samples[0]->memory,
            'Sample memory must retain the logger value.',
        );
    }

    public function testGetDetailFallsBackToHashTimelineUrlWhenModuleIsMissing(): void
    {
        $panel = $this->makePanel(
            ProfilingPanel::class,
        );

        $panel->module = null;

        $this->hydratePanel(
            $panel,
            ProfilingSnapshot::capture(0, 0.0, []),
        );

        self::assertNotEmpty(
            $panel->getDetail(),
            'Missing module must still produce markup with a placeholder timeline link.',
        );
    }

    public function testGetDetailRendersWithCapturedMessages(): void
    {
        $panel = $this->makePanel(
            ProfilingPanel::class,
        );

        $this->hydratePanel(
            $panel,
            ProfilingSnapshot::capture(
                1_048_576,
                0.123,
                [
                    ['app\\token', Logger::LEVEL_PROFILE_BEGIN, 'application', 0.0, []],
                    ['app\\token', Logger::LEVEL_PROFILE_END, 'application', 0.5, []],
                ],
            ),
        );

        self::assertNotEmpty(
            $panel->getDetail(),
            'Detail view must produce markup.',
        );
    }

    public function testGetModelsBuildsTypedRowsFromTimings(): void
    {
        $panel = $this->makePanel(
            ProfilingPanel::class,
        );

        $this->hydratePanel(
            $panel,
            ProfilingSnapshot::capture(
                0,
                0.0,
                [
                    ['app\\sql', Logger::LEVEL_PROFILE_BEGIN, 'application', 0.0, []],
                    ['app\\sql', Logger::LEVEL_PROFILE_END, 'application', 0.005, []],
                ],
            ),
        );

        $models = $panel->getModels();

        self::assertCount(
            1,
            $models,
            'Paired begin/end must yield one row.',
        );

        $row = $models[0];

        self::assertSame(
            'app\\sql',
            $row->info,
            "'info' must round-trip from the begin token.",
        );
        self::assertSame(
            0,
            $row->seq,
            "First row must carry 'seq = 0'.",
        );
    }

    public function testGetModelsCachesTheResult(): void
    {
        $panel = $this->makePanel(
            ProfilingPanel::class,
        );

        $this->hydratePanel(
            $panel,
            ProfilingSnapshot::capture(0, 0.0, []),
        );

        $first = $this->invoke(
            $panel,
            'getModels',
        );
        $second = $this->invoke(
            $panel,
            'getModels',
        );

        self::assertSame(
            $first,
            $second,
            'Cache must return the same list.',
        );
    }

    public function testGetNameAndIcon(): void
    {
        $panel = $this->makePanel(
            ProfilingPanel::class,
        );

        self::assertSame(
            'Profiling',
            $panel->getName(),
            "Display name must be 'Profiling'.",
        );
        self::assertSame(
            'profiling',
            $panel->getToolbarIcon(),
            "Icon key must be 'profiling'.",
        );
    }

    public function testGetToolbarDataBlanksTitleOnSuccess(): void
    {
        $panel = $this->makePanel(
            ProfilingPanel::class,
        );

        $this->hydratePanel(
            $panel,
            ProfilingSnapshot::capture(0, 0.0, []),
        );

        self::assertSame(
            [
                'title' => '',
                'url' => $panel->getUrl(),
                'icon' => 'profiling',
                'items' => [
                    ['title' => 'Total processing time', 'value' => '0 ms'],
                    ['title' => 'Peak memory', 'value' => '0.000 MB'],
                ],
            ],
            $panel->getToolbarData(),
            'Toolbar payload must blank the title on success.',
        );
    }

    public function testGetToolbarDataKeepsTitleOnError(): void
    {
        $panel = $this->makePanel(
            ProfilingPanel::class,
        );

        $panel->setError(ExceptionSnapshot::fromThrowable(new RuntimeException('boom')));

        $payload = $panel->getToolbarData();

        self::assertSame(
            'Profiling',
            $payload['title'] ?? null,
            'Error path must keep the panel title.',
        );
    }

    public function testGetToolbarItemsCarryNoStatusVerdict(): void
    {
        $panel = $this->makePanel(
            ProfilingPanel::class,
        );

        $this->hydratePanel(
            $panel,
            ProfilingSnapshot::capture(2_097_152, 0.25, []),
        );

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Items must be a list.',
        );

        foreach ($items as $item) {
            self::assertIsArray(
                $item,
                'Each chip must be an array.',
            );
            self::assertArrayNotHasKey(
                'status',
                $item,
                'Metrics must render as neutral readouts.',
            );
        }
    }

    public function testGetToolbarItemsEmitsTimeAndMemoryChips(): void
    {
        $panel = $this->makePanel(
            ProfilingPanel::class,
        );

        $this->hydratePanel(
            $panel,
            ProfilingSnapshot::capture(1_234_567, 1.25, []),
        );

        self::assertSame(
            [
                ['title' => 'Total processing time', 'value' => '1,250 ms'],
                ['title' => 'Peak memory', 'value' => Format::bytesToMb(1_234_567, 3)],
            ],
            $this->invoke($panel, 'getToolbarItems'),
            'Toolbar must emit time and memory chips.',
        );
    }
}
