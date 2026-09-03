<?php

declare(strict_types=1);

namespace yii\debug\tests\profile;

use PHPForge\Debug\Helper\Format;
use PHPForge\Debug\Panel\Log\LogSnapshot;
use PHPForge\Debug\Panel\Profile\ProfilingSnapshot;
use PHPForge\Debug\Storage\ExceptionSnapshot;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use RuntimeException;
use Yii;
use yii\debug\panels\ProfilingPanel;
use yii\debug\tests\provider\VisibilityProvider;
use yii\debug\tests\support\stub\CapturingView;
use yii\debug\tests\support\TestCase;
use yii\log\Logger;

use function strpos;
use function substr_count;

/**
 * Unit tests for {@see ProfilingPanel} covering the typed row decoration, the toolbar items (time + memory), the
 * title-blanking on the toolbar payload, and snapshot hydration.
 *
 * {@see VisibilityProvider} for method contract data providers.
 */
#[Group('panel')]
#[Group('profile')]
final class ProfilingPanelTest extends TestCase
{
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

    /**
     * @param class-string $class
     * @param 'protected'|'public' $expected
     */
    #[DataProviderExternal(VisibilityProvider::class, 'profilingPanelContracts')]
    public function testExtensionMethodKeepsDeclaredVisibility(string $class, string $method, string $expected): void
    {
        self::assertMethodVisibility(
            $class,
            $method,
            $expected,
        );
    }

    public function testGetDetailBannerUsesNormalizedProfilingFilters(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        Yii::$app->getRequest()->setQueryParams(
            [
                'tag' => 'profile-tag',
                'panel' => 'profiling',
                'Profile' => ['duration' => 'invalid', 'category' => 'application'],
            ],
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

        $detail = $panel->getDetail();

        self::assertStringContainsString(
            '1 filter active',
            $detail,
            'Only filters retained by ProfileSearch must count as active.',
        );
        self::assertStringContainsString(
            'aria-label="Remove category: application filter"',
            $detail,
            'The valid category filter must remain in the active-filter banner.',
        );
        self::assertStringNotContainsString(
            'aria-label="Remove duration: invalid filter"',
            $detail,
            'The ignored invalid duration must not appear in the active-filter banner.',
        );
    }

    public function testGetDetailKeepsFiltersAndRendersFilteredEmptyStateWhenSpansWereCaptured(): void
    {
        $panel = $this->makePanel(
            ProfilingPanel::class,
        );

        Yii::$app->getRequest()->setQueryParams(
            [
                'tag' => 'profile-tag',
                'panel' => 'profiling',
                'Profile' => ['category' => 'missing category'],
            ],
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

        $detail = $panel->getDetail();

        self::assertStringContainsString(
            '<strong>0</strong> of 1 span',
            $detail,
            'Summary must distinguish the filtered result count from the captured span count.',
        );
        self::assertStringContainsString(
            'No spans match the active filters',
            $detail,
            'A zero-result filter must not be presented as an empty capture.',
        );
        self::assertStringNotContainsString(
            'No profiling data captured',
            $detail,
            'Captured spans must keep the capture-empty explanation hidden.',
        );
        self::assertStringContainsString(
            'value="missing category"',
            $detail,
            'The shared filter must retain the submitted value when no span matches.',
        );
        self::assertStringContainsString(
            '>Clear all<',
            $detail,
            'The filtered empty state must expose a clear-all action.',
        );
    }

    public function testGetDetailPassesExactUnifiedViewData(): void
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
            Format::bytesToMb(1_234_567, 2),
            $view->renderParams['memory'] ?? null,
            'Detail view must receive the exact memory in megabytes.',
        );
        self::assertArrayHasKey(
            'timeline',
            $view->renderParams,
            'Detail view must receive the rendered Timeline state.',
        );
        self::assertArrayNotHasKey(
            'timelineUrl',
            $view->renderParams,
            'Unified detail view must not receive a link to a duplicate Timeline panel.',
        );
    }

    public function testGetDetailRendersSharedFiltersTimelineAndDetails(): void
    {
        $panel = $this->makePanel(
            ProfilingPanel::class,
        );

        $panel->setRequestSummary($this->requestSummary(overrides: ['time' => 1_700_000_000.0]));

        $this->hydratePanel(
            $panel,
            ProfilingSnapshot::capture(
                1_048_576,
                0.123,
                [
                    [
                        'home-action',
                        Logger::LEVEL_PROFILE_BEGIN,
                        'application',
                        1_700_000_000.0,
                        [],
                        900_000,
                    ],
                    [
                        'home-action',
                        Logger::LEVEL_PROFILE_END,
                        'application',
                        1_700_000_000.02,
                        [],
                        1_000_000,
                    ],
                ],
            ),
        );

        $detail = $panel->getDetail();

        self::assertStringContainsString(
            '<strong>1</strong> span',
            $detail,
            'Equal visible and captured counts must use the concise singular summary.',
        );
        self::assertStringContainsString(
            'name="Profile[duration]"',
            $detail,
            'Timeline and details must share the profiling minimum-duration filter.',
        );
        self::assertStringContainsString(
            'name="Profile[category]"',
            $detail,
            'Timeline and details must share the profiling category filter.',
        );
        self::assertStringContainsString(
            'name="Profile[info]"',
            $detail,
            'Timeline and details must share the profiling info filter.',
        );
        self::assertMatchesRegularExpression(
            '/<h2>\s*Timeline\s*<\/h2>/',
            $detail,
            'Unified view must render the Timeline section.',
        );
        self::assertMatchesRegularExpression(
            '/<h2>\s*Details\s*<\/h2>/',
            $detail,
            'Unified view must render the details section.',
        );

        $timelinePosition = strpos($detail, '<section class="yii-debug-tl">');
        $detailsPosition = strpos($detail, '<header class="yii-debug-section-header">');

        self::assertIsInt(
            $timelinePosition,
            'Unified view must contain the Timeline chart.',
        );
        self::assertIsInt(
            $detailsPosition,
            'Unified view must contain the details heading.',
        );
        self::assertLessThan(
            $detailsPosition,
            $timelinePosition,
            'Timeline chart must render above the profiling details.',
        );
        self::assertStringNotContainsString(
            'Open timeline',
            $detail,
            'Unified view must not link to a duplicate Timeline panel.',
        );
        self::assertStringNotContainsString(
            '<tr class="filters">',
            $detail,
            'Details grid must not duplicate the shared filter form.',
        );
    }

    public function testGetDetailRendersTimelineUnavailableWhenModuleAndSummaryAreMissing(): void
    {
        $panel = $this->makePanel(
            ProfilingPanel::class,
        );

        $panel->module = null;

        $this->hydratePanel(
            $panel,
            ProfilingSnapshot::capture(
                1_048_576,
                0.1,
                [
                    ['profile', Logger::LEVEL_PROFILE_BEGIN, 'application', 1.0, []],
                    ['profile', Logger::LEVEL_PROFILE_END, 'application', 1.01, []],
                ],
            ),
        );

        self::assertStringContainsString(
            'Timeline unavailable',
            $panel->getDetail(),
            'Missing module and request summary must produce an explicit Timeline unavailable state.',
        );
    }

    public function testGetMemoryUsageDefaultsToZero(): void
    {
        $panel = $this->makePanel(
            ProfilingPanel::class,
        );

        self::assertSame(
            0,
            $panel->getMemoryUsage(),
            'Unhydrated memory usage must default to zero.',
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

    public function testTimelineMergesProfilingAndLogMemorySamples(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        $this->hydratePanel(
            $panel,
            ProfilingSnapshot::capture(
                1_048_576,
                0.1,
                [
                    ['profile', Logger::LEVEL_PROFILE_BEGIN, 'application', 1.0, [], 100],
                    ['profile', Logger::LEVEL_PROFILE_END, 'application', 1.1, [], 200],
                ],
            ),
        );

        $logPanel = $panel->module?->panels['log'] ?? null;

        self::assertNotNull($logPanel, 'Default module must register the log panel.');

        $this->hydratePanel(
            $logPanel,
            LogSnapshot::capture(
                [['message', Logger::LEVEL_INFO, 'application', 1.05, [], 150]],
            ),
        );

        $samples = $this->invoke($panel, 'timelineMemorySamples');

        self::assertIsArray(
            $samples,
            'Timeline memory samples must be returned as a list.',
        );
        self::assertCount(
            3,
            $samples,
            'Timeline memory graph must merge samples from the profiling and log panels.',
        );
    }

    public function testTimelineShowsShortClassNameAndKeepsFullCategoryOnHover(): void
    {
        $panel = $this->makePanel(
            ProfilingPanel::class,
            ['view' => CapturingView::class],
        );

        $panel->setRequestSummary($this->requestSummary(overrides: ['time' => 1_700_000_000.0]));

        $this->hydratePanel(
            $panel,
            ProfilingSnapshot::capture(
                1_048_576,
                0.1,
                [
                    [
                        'home-action',
                        Logger::LEVEL_PROFILE_BEGIN,
                        'App\\Web\\Workbench\\HomeAction::__invoke',
                        1_700_000_000.0,
                        [],
                    ],
                    [
                        'home-action',
                        Logger::LEVEL_PROFILE_END,
                        'App\\Web\\Workbench\\HomeAction::__invoke',
                        1_700_000_000.02,
                        [],
                    ],
                ],
            ),
        );

        $panel->getDetail();

        $view = Yii::$app->getView();

        self::assertInstanceOf(
            CapturingView::class,
            $view,
            'Detail view must render through the capturing view.',
        );

        $timeline = $view->renderParams['timeline'] ?? null;

        self::assertIsString(
            $timeline,
            'Detail view must receive rendered Timeline markup.',
        );
        self::assertStringNotContainsString(
            '<wbr>',
            $timeline,
            'Timeline category labels must remain on one line.',
        );
        self::assertStringContainsString(
            '<span class="yii-debug-tl-name" title="App\\Web\\Workbench\\HomeAction::__invoke">'
            . '<strong>HomeAction</strong></span>',
            $timeline,
            'Timeline must keep only the short class visible and expose the full category through its hover title.',
        );
    }

    public function testTimelineUsesAllFilteredRowsRegardlessOfGridPaginationAndSort(): void
    {
        $panel = $this->makePanel(ProfilingPanel::class);

        Yii::$app->getRequest()->setQueryParams(
            [
                'panel' => 'profiling',
                'per-page' => '1',
                'sort' => '-info',
            ],
        );

        $panel->setRequestSummary($this->requestSummary(overrides: ['time' => 1_700_000_000.0]));

        $this->hydratePanel(
            $panel,
            ProfilingSnapshot::capture(
                2_097_152,
                0.1,
                [
                    ['first', Logger::LEVEL_PROFILE_BEGIN, 'application', 1_700_000_000.0, []],
                    ['first', Logger::LEVEL_PROFILE_END, 'application', 1_700_000_000.01, []],
                    ['second', Logger::LEVEL_PROFILE_BEGIN, 'application', 1_700_000_000.02, []],
                    ['second', Logger::LEVEL_PROFILE_END, 'application', 1_700_000_000.04, []],
                ],
            ),
        );

        $detail = $panel->getDetail();

        self::assertSame(
            2,
            substr_count($detail, 'role="listitem"'),
            'Timeline must use every filtered span rather than the paginated and sorted grid page.',
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
            Format::bytesToMb(0, 2),
            $view->renderParams['memory'] ?? null,
            'Detail view must receive zero peak memory at the unified view precision.',
        );
        self::assertArrayNotHasKey(
            'timelineUrl',
            $view->renderParams,
            'Unhydrated unified detail must not receive a duplicate Timeline URL.',
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
}
