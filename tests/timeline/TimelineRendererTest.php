<?php

declare(strict_types=1);

namespace yii\debug\tests\timeline;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\debug\models\search\TimelineSearch;
use yii\debug\models\timeline\DataProvider;
use yii\debug\Module;
use yii\debug\panels\timeline\TimelineRenderer;
use yii\debug\panels\TimelinePanel;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see TimelineRenderer} covering the summary header (totals + peak memory + span count), the filter
 * form layout, the chart axis / row / memory footer composition and the empty-state hint gating.
 */
#[Group('panel')]
#[Group('timeline')]
final class TimelineRendererTest extends TestCase
{
    public function testRenderChartIncludesMemoryFooterWhenSvgHasPoints(): void
    {
        $panel = $this->stubPanel(100.0, 2_097_152);

        // Force the memoized SVG to have plotted points so 'renderChart' attaches the memory footer.
        $svg = $panel->getSvg();

        $this->setInaccessibleProperty(
            $svg,
            'points',
            [[0.0, 50.0], [1.0, 80.0]],
        );

        $dataProvider = $this->makeDataProvider(
            $panel,
            [['category' => 'yii\\db\\Command::query', 'timestamp' => 0.0, 'duration' => 0.05]],
        );

        $html = TimelineRenderer::renderChart($panel, $dataProvider);

        self::assertStringContainsString(
            'yii-debug-tl-memory',
            $html,
            'Memory footer must surface when the SVG memory chart has points.',
        );
        self::assertStringContainsString(
            'yii-debug-tl-memory-peak',
            $html,
            'Peak-memory chip must appear inside the memory footer.',
        );
    }

    public function testRenderChartReturnsEmptyStringWhenDataProviderHasNoModels(): void
    {
        $panel = $this->stubPanel(10.0, 1);

        $dataProvider = $this->makeDataProvider(
            $panel,
            [],
        );

        self::assertSame(
            '',
            TimelineRenderer::renderChart($panel, $dataProvider),
            'Empty data provider must skip the chart entirely.',
        );
    }

    public function testRenderChartWiresAxisTicksAndSpanRows(): void
    {
        $panel = $this->stubPanel(100.0, 1048576);

        $dataProvider = $this->makeDataProvider(
            $panel,
            [
                ['category' => 'yii\\db\\Command::query', 'timestamp' => 0.0, 'duration' => 0.05],
                ['category' => 'yii\\base\\View::render', 'timestamp' => 0.05, 'duration' => 0.03],
            ],
        );

        $html = TimelineRenderer::renderChart($panel, $dataProvider);

        self::assertStringContainsString(
            'class="yii-debug-tl-axis"',
            $html,
            'Chart must surface the ruler axis header.',
        );
        self::assertStringContainsString(
            'class="yii-debug-tl-rows"',
            $html,
            'Chart must surface the rows container.',
        );
        self::assertStringContainsString(
            'yii-debug-tl-row-info',
            $html,
            'DB span must carry the info variant.',
        );
        self::assertStringContainsString(
            'yii-debug-tl-row-warning',
            $html,
            'View span must carry the warning variant.',
        );
    }

    public function testRenderEmptyHintRendersProfilingPanelLink(): void
    {
        $panel = $this->stubPanel(10.0, 1);

        $dataProvider = $this->makeDataProvider(
            $panel,
            [],
        );

        $html = TimelineRenderer::renderEmptyHint($panel, $dataProvider);

        self::assertStringContainsString(
            'No spans matched your filter.',
            $html,
            'Empty state must show the dedicated title.',
        );
        self::assertStringContainsString(
            'Profiling panel',
            $html,
            'Hint must link the developer to the Profiling panel.',
        );
        self::assertStringContainsString(
            'panel=profiling',
            $html,
            "Profiling link must carry the 'panel=profiling' query."
        );
    }

    public function testRenderEmptyHintReturnsEmptyStringWhenChartHasData(): void
    {
        $panel = $this->stubPanel(100.0, 1048576);

        $dataProvider = $this->makeDataProvider(
            $panel,
            [
                ['category' => 'x', 'timestamp' => 0.0, 'duration' => 0.05],
            ],
        );

        self::assertSame(
            '',
            TimelineRenderer::renderEmptyHint($panel, $dataProvider),
            'Non-empty data must skip the hint to avoid duplicate noise.',
        );
    }

    public function testRenderFilterFormWiresHiddenTagAndCategoryInputs(): void
    {
        $panel = $this->stubPanel(10.0, 1);

        $panel->tag = 'request-tag-123';

        $searchModel = new TimelineSearch();

        $searchModel->category = 'yii\\db';
        $searchModel->duration = '5';

        $html = TimelineRenderer::renderFilterForm($panel, $searchModel);

        self::assertStringContainsString(
            'name="r"',
            $html,
            "Hidden 'r' input must be present.",
        );
        self::assertStringContainsString(
            'name="panel"',
            $html,
            "Hidden 'panel' input must be present.",
        );
        self::assertStringContainsString(
            'value="request-tag-123"',
            $html,
            'Hidden tag input must carry the active tag.',
        );
        self::assertStringContainsString(
            'name="TimelineSearch[duration]"',
            $html,
            'Duration input must surface in the form.',
        );
        self::assertStringContainsString(
            'value="5"',
            $html,
            'Duration value must echo the captured filter.',
        );
        self::assertStringContainsString(
            'value="yii\\db"',
            $html,
            'Category value must echo the captured filter.',
        );
        self::assertStringContainsString(
            'Apply',
            $html,
            "Submit button must render the 'Apply' label.",
        );
    }

    public function testRenderRowsSkipsNonArrayModels(): void
    {
        $panel = $this->stubPanel(100.0, 1);

        $dataProvider = $this->makeDataProvider($panel, []);

        // Inject a non-array model via reflection so 'renderRows' hits its defensive `continue`.
        $this->setInaccessibleProperty(
            $dataProvider,
            '_models',
            ['not-an-array', 42],
        );

        $html = TimelineRenderer::renderChart($panel, $dataProvider);

        self::assertStringContainsString(
            'class="yii-debug-tl-rows"',
            $html,
            "Chart must still render the rows container even when 'models' contains non-array entries.",
        );
        self::assertStringNotContainsString(
            'yii-debug-tl-row-',
            $html,
            "Non-array 'models' must be skipped via the defensive 'continue'.",
        );
    }

    public function testRenderSummaryEchoesTotalsFromPanelAndDataProvider(): void
    {
        $panel = $this->stubPanel(123.456, 2 * 1048576);

        $dataProvider = $this->makeDataProvider(
            $panel,
            [
                ['category' => 'a', 'timestamp' => 0.0, 'duration' => 0.01],
                ['category' => 'b', 'timestamp' => 0.01, 'duration' => 0.01],
                ['category' => 'c', 'timestamp' => 0.02, 'duration' => 0.01],
            ],
        );

        $html = TimelineRenderer::renderSummary($panel, $dataProvider);

        self::assertStringContainsString(
            'ms total',
            $html,
            'Summary must label the total-duration figure.'
        );
        self::assertStringContainsString(
            'peak memory',
            $html,
            'Summary must label the peak-memory figure.'
        );
        self::assertStringContainsString(
            '2.00 MB',
            $html,
            'Peak memory must render with two-decimal MB precision.'
        );
        self::assertStringContainsString(
            '>3</strong>',
            $html,
            'Span count must reflect the data provider model count.'
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockWebApplication();
    }

    protected function tearDown(): void
    {
        $this->destroyApplication();

        parent::tearDown();
    }

    /**
     * @param list<array<string, mixed>> $models
     */
    private function makeDataProvider(TimelinePanel $panel, array $models): DataProvider
    {
        return new DataProvider($panel, ['allModels' => $models]);
    }

    private function stubPanel(float $duration, int $memory): TimelinePanel
    {
        $module = new Module('debug', null, ['dataPath' => '@runtime/debug']);

        $module->allowedIPs = ['*'];

        Yii::$app->setModule('debug', $module);

        $module->bootstrap(Yii::$app);

        $panel = $module->panels['timeline'] ?? null;

        self::assertInstanceOf(
            TimelinePanel::class,
            $panel,
            "Module must register a 'TimelinePanel'.",
        );

        $start = 1_700_000_000.0;

        $panel->load(
            [
                'start' => $start,
                'end' => $start + $duration / 1000,
                'memory' => $memory,
            ],
        );

        return $panel;
    }
}
