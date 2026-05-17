<?php

declare(strict_types=1);

namespace yii\debug\tests\history;

use PHPUnit\Framework\Attributes\Group;
use Yii;
use yii\debug\controllers\DefaultController;
use yii\debug\models\search\DebugSearch;
use yii\debug\Module;
use yii\debug\panels\DbPanel;
use yii\debug\tests\support\TestCase;
use yii\debug\widgets\history\{HistoryRow, HistoryRowRenderer, HistoryStatusBucket, HistorySummary};

/**
 * Unit tests for {@see HistoryRowRenderer} covering the per-column rendering helpers, the row-options builder
 * (`data-yii-debug-*` attributes for the sidebar cursor JS) and the summary header composition.
 */
#[Group('panel')]
#[Group('history')]
final class HistoryRowRendererTest extends TestCase
{
    public function testBuildRowOptionsAddsDataAttributesForCursorJs(): void
    {
        $row = HistoryRow::from(
            [
                'tag' => 'abc',
                'method' => 'GET',
                'url' => '/path',
                'statusCode' => 200,
                'time' => 1_700_000_000,
                'ajax' => true,
            ],
        );

        $options = HistoryRowRenderer::buildRowOptions($row, new DebugSearch());

        self::assertSame(
            [
                'tag' => 'abc',
                'method' => 'GET',
                'url' => '/path',
                'status' => '200',
                'ajax' => '1',
            ],
            [
                'tag' => $options['data-yii-debug-tag'] ?? null,
                'method' => $options['data-yii-debug-method'] ?? null,
                'url' => $options['data-yii-debug-url'] ?? null,
                'status' => $options['data-yii-debug-status'] ?? null,
                'ajax' => $options['data-yii-debug-ajax'] ?? null,
            ],
            'Row data-yii-debug-* attributes must mirror the typed row.',
        );
    }

    public function testBuildRowOptionsAlwaysCarriesRowLinkClass(): void
    {
        $options = HistoryRowRenderer::buildRowOptions(
            HistoryRow::from([]),
            new DebugSearch(),
        );

        self::assertArrayHasKey(
            'class',
            $options,
            'Row options must carry a class entry.',
        );
        self::assertIsString(
            $options['class'],
            'class entry must be a string.',
        );
        self::assertStringContainsString(
            'yii-debug-row-link',
            $options['class'],
            'class must include the JS row-link hook.',
        );
    }

    public function testBuildRowOptionsFlagsCriticalStatusCodesWithDangerHighlight(): void
    {
        $row = HistoryRow::from(
            [
                'tag' => 'critical',
                'statusCode' => 500,
            ],
        );

        $searchModel = new DebugSearch();
        $options = HistoryRowRenderer::buildRowOptions($row, $searchModel);

        self::assertIsString(
            $options['class'] ?? null,
            'class entry must be a string.',
        );
        self::assertStringContainsString(
            'yii-debug-row-danger',
            $options['class'],
            'Critical status codes must surface the danger highlight class alongside the row-link hook.',
        );
    }

    public function testRenderAjaxCellMapsBoolToYesOrNo(): void
    {
        self::assertSame(
            'Yes',
            HistoryRowRenderer::renderAjaxCell(HistoryRow::from(['ajax' => true])),
            "Boolean ajax value must map to 'Yes'.",
        );
        self::assertSame(
            'No',
            HistoryRowRenderer::renderAjaxCell(HistoryRow::from(['ajax' => false])),
            "Boolean ajax value must map to 'No'.",
        );
    }

    public function testRenderDurationCellFormatsMilliseconds(): void
    {
        self::assertSame(
            '125 ms',
            HistoryRowRenderer::renderDurationCell(HistoryRow::from(['processingTime' => 0.125])),
            "Seconds must format as 'X ms'.",
        );
    }

    public function testRenderDurationCellShowsNotSetWhenMissing(): void
    {
        $html = HistoryRowRenderer::renderDurationCell(HistoryRow::from([]));

        self::assertStringContainsString(
            '(not set)',
            $html,
            'Missing duration must surface the muted placeholder.',
        );
    }

    public function testRenderMemoryCellFormatsMb(): void
    {
        self::assertSame(
            '2.000 MB',
            HistoryRowRenderer::renderMemoryCell(HistoryRow::from(['peakMemory' => 2097152])),
            "Bytes must format as 'X.XXX MB'.",
        );
    }

    public function testRenderMemoryCellShowsNotSetWhenMissing(): void
    {
        $html = HistoryRowRenderer::renderMemoryCell(
            HistoryRow::from([]),
        );

        self::assertStringContainsString(
            '(not set)',
            $html,
            'Missing peak memory must surface the muted placeholder.',
        );
    }

    public function testRenderSqlCountCellEmitsWarningGlyphWhenAboveThreshold(): void
    {
        $row = HistoryRow::from(
            [
                'tag' => 'flood',
                'sqlCount' => 500,
                'excessiveCallersCount' => 0,
            ],
        );

        $dbPanel = new DbPanel();

        $dbPanel->criticalQueryThreshold = 100;

        $html = HistoryRowRenderer::renderSqlCountCell($row, $dbPanel);

        self::assertStringContainsString(
            '⚠',
            $html,
            'Counts above the threshold must surface the warning glyph.',
        );
        self::assertStringContainsString(
            'Too many queries',
            $html,
            'Warning tooltip must explain the threshold breach.',
        );
    }

    public function testRenderSqlCountCellPluralizesExcessiveCallersCount(): void
    {
        $row = HistoryRow::from(
            [
                'tag' => 'flood',
                'sqlCount' => 10,
                'excessiveCallersCount' => 4,
            ],
        );

        $dbPanel = new DbPanel();

        $dbPanel->criticalQueryThreshold = 100;

        $html = HistoryRowRenderer::renderSqlCountCell($row, $dbPanel);

        self::assertStringContainsString(
            '4 callers are making too many calls.',
            $html,
            'Multiple excessive callers must surface the plural tooltip form.',
        );
    }

    public function testRenderSqlCountCellRendersPlainCountWhenBelowThreshold(): void
    {
        $row = HistoryRow::from(
            [
                'tag' => 'low',
                'sqlCount' => 3,
                'excessiveCallersCount' => 0,
            ],
        );

        $dbPanel = new DbPanel();

        $dbPanel->criticalQueryThreshold = 100;

        $html = HistoryRowRenderer::renderSqlCountCell($row, $dbPanel);

        self::assertStringContainsString(
            '>3<',
            $html,
            'Plain SQL count must surface as the bare integer.',
        );
        self::assertStringNotContainsString(
            '⚠',
            $html,
            'Counts below the threshold must NOT carry the warning glyph.',
        );
    }

    public function testRenderSqlCountCellSingularizesSingleExcessiveCaller(): void
    {
        $row = HistoryRow::from(
            [
                'tag' => 'flood',
                'sqlCount' => 10,
                'excessiveCallersCount' => 1,
            ],
        );

        $dbPanel = new DbPanel();

        $dbPanel->criticalQueryThreshold = 100;

        $html = HistoryRowRenderer::renderSqlCountCell($row, $dbPanel);

        self::assertStringContainsString(
            '1 caller is making too many calls.',
            $html,
            'A single excessive caller must surface the singular tooltip form.',
        );
    }

    public function testRenderStatusCellMapsCommandWithZeroToSuccess(): void
    {
        self::assertStringContainsString(
            'yii-debug-badge--success',
            HistoryRowRenderer::renderStatusCell(HistoryRow::from(['method' => 'COMMAND', 'statusCode' => 0])),
            "COMMAND with status '0' must map to the success variant.",
        );
    }

    public function testRenderStatusCellMapsRangeToVariant(): void
    {
        self::assertStringContainsString(
            'yii-debug-badge--success',
            HistoryRowRenderer::renderStatusCell(HistoryRow::from(['statusCode' => 200])),
            "Status code '200' must map to the success variant.",
        );
        self::assertStringContainsString(
            'yii-debug-badge--info',
            HistoryRowRenderer::renderStatusCell(HistoryRow::from(['statusCode' => 301])),
            "Status code '301' must map to the info variant.",
        );
        self::assertStringContainsString(
            'yii-debug-badge--danger',
            HistoryRowRenderer::renderStatusCell(HistoryRow::from(['statusCode' => 500])),
            "Status code '500' must map to the danger variant.",
        );
    }

    public function testRenderSummaryEchoesBucketPills(): void
    {
        $summary = new HistorySummary(
            totalRequests: 5,
            statusBuckets: [
                new HistoryStatusBucket(label: '2xx', count: 4, sampleCode: 200, variant: 'success'),
                new HistoryStatusBucket(label: '4xx', count: 1, sampleCode: 404, variant: 'warn'),
            ],
            statusCodeFilter: null,
        );
        $html = HistoryRowRenderer::renderSummary($summary);

        self::assertStringContainsString(
            'captured request',
            $html,
            'Summary must label the total figure.',
        );
        self::assertStringContainsString(
            'yii-debug-grid-summary-stat-success',
            $html,
            "'2xx' pill must carry the success variant.",
        );
        self::assertStringContainsString(
            'yii-debug-grid-summary-stat-warn',
            $html,
            "'4xx' pill must carry the warn variant."
        );
        self::assertStringContainsString(
            '2xx',
            $html,
            'Bucket labels must surface.',
        );
    }

    public function testRenderSummaryReturnsEmptyWhenNoRequestsCaptured(): void
    {
        $summary = new HistorySummary(
            totalRequests: 0,
            statusBuckets: [],
            statusCodeFilter: null,
        );

        self::assertSame(
            '',
            HistoryRowRenderer::renderSummary($summary),
            'Empty manifest must skip the header entirely.',
        );
    }

    public function testRenderTagCellLinksToPanelView(): void
    {
        $html = HistoryRowRenderer::renderTagCell(
            HistoryRow::from(['tag' => 'abc']),
        );

        self::assertStringContainsString(
            'yii-debug-tag-link',
            $html,
            'Tag link must carry the tag-link CSS class.',
        );
        self::assertStringContainsString('abc', $html, 'Tag value must surface inside the link.');
    }

    public function testRenderTimeCellRendersCompactClockWithFullTooltip(): void
    {
        $row = HistoryRow::from(
            [
                'time' => 1_700_000_000,
            ],
        );

        $html = HistoryRowRenderer::renderTimeCell($row);

        self::assertStringContainsString(
            'yii-debug-nowrap',
            $html,
            'Time cell must carry the nowrap CSS class.',
        );
        self::assertStringContainsString(
            'title="2023-11-14',
            $html,
            'Time cell must carry the full datetime tooltip.',
        );
    }

    public function testRenderTimeCellShowsNotSetForZeroTimestamp(): void
    {
        $html = HistoryRowRenderer::renderTimeCell(
            HistoryRow::from(['time' => 0]),
        );

        self::assertStringContainsString(
            '(not set)',
            $html,
            'Zero timestamps must surface the muted placeholder.',
        );
    }

    public function testRenderUrlCellWrapsUrlInTitleSpan(): void
    {
        $html = HistoryRowRenderer::renderUrlCell(
            HistoryRow::from(['url' => 'http://example.test/path']),
        );

        self::assertStringContainsString(
            'yii-debug-url-cell',
            $html,
            'URL cell must carry the dedicated class.',
        );
        self::assertStringContainsString(
            'http://example.test/path',
            $html,
            'URL value must render inside the cell.',
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockWebApplication();

        $module = new Module('debug', null, ['dataPath' => '@runtime/debug']);

        $module->allowedIPs = ['*'];

        Yii::$app->setModule('debug', $module);

        $module->bootstrap(Yii::$app);

        Yii::$app->controller = new DefaultController('default', $module);
    }

    protected function tearDown(): void
    {
        $this->destroyApplication();

        parent::tearDown();
    }
}
