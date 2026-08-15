<?php

declare(strict_types=1);

namespace yii\debug\tests\history;

use PHPForge\Debug\Storage\RequestSummary;
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
        $row = self::row([
            'tag' => 'abc',
            'method' => 'GET',
            'url' => '/path',
            'statusCode' => 200,
            'time' => 1_700_000_000,
            'ajax' => true,
        ]);

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

    public function testBuildRowOptionsFlagsCriticalStatusCodesWithDangerHighlight(): void
    {
        $row = self::row([
            'tag' => 'critical',
            'statusCode' => 500,
        ]);

        $searchModel = new DebugSearch();
        $options = HistoryRowRenderer::buildRowOptions($row, $searchModel);

        self::assertIsString(
            $options['class'] ?? null,
            'class entry must be a string.',
        );
        self::assertStringContainsString(
            'yii-debug-row-danger',
            $options['class'],
            'Critical status codes must surface the danger highlight class.',
        );
    }

    public function testRenderAjaxCellMapsBoolToYesOrNo(): void
    {
        self::assertSame(
            'Yes',
            HistoryRowRenderer::renderAjaxCell(self::row(['ajax' => true])),
            "Boolean ajax value must map to 'Yes'.",
        );
        self::assertSame(
            'No',
            HistoryRowRenderer::renderAjaxCell(self::row(['ajax' => false])),
            "Boolean ajax value must map to 'No'.",
        );
    }

    public function testRenderDurationCellFormatsMilliseconds(): void
    {
        self::assertSame(
            '125 ms',
            HistoryRowRenderer::renderDurationCell(self::row(['processingTime' => 0.125]), 0.0),
            "Seconds must format as 'X ms'.",
        );
        self::assertSame(
            '2,000 ms',
            HistoryRowRenderer::renderDurationCell(self::row(['processingTime' => 2.0]), 0.0),
            'Second-scale durations must keep the thousands separator.',
        );
    }

    public function testRenderDurationCellScalesGaugeAgainstPageMaximum(): void
    {
        $html = HistoryRowRenderer::renderDurationCell(self::row(['processingTime' => 0.125]), 0.25);

        self::assertSame(
            '<span class="yii-debug-gauge" style=\'--yii-debug-gauge: 50%;\'>'
            . '<span class="yii-debug-gauge-value">125 ms</span>'
            . '<span class="yii-debug-gauge-bar" aria-hidden="true"></span>'
            . '</span>',
            $html,
            'Rail must sit at half the page maximum.',
        );
        self::assertStringContainsString(
            '--yii-debug-gauge: 100%;',
            HistoryRowRenderer::renderDurationCell(self::row(['processingTime' => 0.25]), 0.25),
            'The slowest row must fill its rail.',
        );
        self::assertStringContainsString(
            '--yii-debug-gauge: 0%;',
            HistoryRowRenderer::renderDurationCell(self::row(['processingTime' => 0.0]), 0.25),
            'A zero measurement must show an empty rail.',
        );
    }

    public function testRenderDurationCellShowsNotSetWhenMissing(): void
    {
        $html = HistoryRowRenderer::renderDurationCell(self::row([]), 0.25);

        self::assertStringContainsString(
            '(not set)',
            $html,
            'Missing duration must surface the muted placeholder.',
        );
        self::assertStringNotContainsString(
            'yii-debug-gauge',
            $html,
            'Missing duration must not draw a rail.',
        );
    }

    public function testRenderMemoryCellFormatsMb(): void
    {
        self::assertSame(
            '2.000 MB',
            HistoryRowRenderer::renderMemoryCell(self::row(['peakMemory' => 2097152]), 0),
            "Bytes must format as 'X.XXX MB'.",
        );
    }

    public function testRenderMemoryCellScalesGaugeAgainstPageMaximum(): void
    {
        $html = HistoryRowRenderer::renderMemoryCell(self::row(['peakMemory' => 2097152]), 4194304);

        self::assertStringContainsString(
            '--yii-debug-gauge: 50%;',
            $html,
            'Rail must sit at half the page maximum.',
        );
        self::assertStringContainsString(
            '2.000 MB',
            $html,
            'Readout must keep its formatted value.',
        );
    }

    public function testRenderMemoryCellShowsNotSetWhenMissing(): void
    {
        $html = HistoryRowRenderer::renderMemoryCell(
            self::row([]),
            4194304,
        );

        self::assertStringContainsString(
            '(not set)',
            $html,
            'Missing peak memory must surface the muted placeholder.',
        );
        self::assertStringNotContainsString(
            'yii-debug-gauge',
            $html,
            'Missing peak memory must not draw a rail.',
        );
    }

    public function testRenderMethodCellRendersVocabularyColoredText(): void
    {
        self::assertSame(
            '<span class="yii-debug-method yii-debug-verb-get">GET</span>',
            HistoryRowRenderer::renderMethodCell(self::row(['method' => 'GET'])),
            "GET must wear the 'get' verb class.",
        );
        self::assertStringContainsString(
            'yii-debug-verb-put',
            HistoryRowRenderer::renderMethodCell(self::row(['method' => 'PATCH'])),
            "PATCH must share the 'put' verb hue.",
        );
        self::assertStringContainsString(
            'yii-debug-verb-other',
            HistoryRowRenderer::renderMethodCell(self::row(['method' => 'COMMAND'])),
            "COMMAND must fall back to the 'other' verb.",
        );
    }

    public function testRenderMethodCellReturnsEmptyStringForUncapturedMethod(): void
    {
        self::assertSame(
            '',
            HistoryRowRenderer::renderMethodCell(self::row(['method' => ''])),
            'An uncaptured method must render nothing.',
        );
    }

    public function testRenderSqlCountCellEmitsWarningGlyphWhenAboveThreshold(): void
    {
        $row = self::row([
            'tag' => 'flood',
            'sqlCount' => 500,
            'excessiveCallersCount' => 0,
        ]);

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
        $row = self::row([
            'tag' => 'flood',
            'sqlCount' => 10,
            'excessiveCallersCount' => 4,
        ]);

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
        $row = self::row([
            'tag' => 'low',
            'sqlCount' => 3,
            'excessiveCallersCount' => 0,
        ]);

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
        $row = self::row([
            'tag' => 'flood',
            'sqlCount' => 10,
            'excessiveCallersCount' => 1,
        ]);

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
            'yii-debug-status-2xx',
            HistoryRowRenderer::renderStatusCell(self::row(['method' => 'COMMAND', 'statusCode' => 0])),
            "COMMAND with status '0' must display as a '2xx'.",
        );
    }

    public function testRenderStatusCellMapsRangeToStatusClass(): void
    {
        self::assertStringContainsString(
            'yii-debug-badge yii-debug-status-2xx',
            HistoryRowRenderer::renderStatusCell(self::row(['statusCode' => 200])),
            "Status code '200' must map to '2xx'.",
        );
        self::assertStringContainsString(
            'yii-debug-status-3xx',
            HistoryRowRenderer::renderStatusCell(self::row(['statusCode' => 301])),
            "Status code '301' must map to '3xx'.",
        );
        self::assertStringContainsString(
            'yii-debug-status-4xx',
            HistoryRowRenderer::renderStatusCell(self::row(['statusCode' => 404])),
            "Status code '404' must map to '4xx'.",
        );
        self::assertStringContainsString(
            'yii-debug-status-5xx',
            HistoryRowRenderer::renderStatusCell(self::row(['statusCode' => 500])),
            "Status code '500' must map to '5xx'.",
        );
    }

    public function testRenderSummaryEchoesBucketPills(): void
    {
        $summary = new HistorySummary(
            totalRequests: 5,
            statusBuckets: [
                new HistoryStatusBucket(label: '2xx', count: 4, sampleCode: 200, variant: '2xx'),
                new HistoryStatusBucket(label: '4xx', count: 1, sampleCode: 404, variant: '4xx'),
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
            'yii-debug-grid-summary-stat-2xx',
            $html,
            "'2xx' pill must carry the '2xx' status class.",
        );
        self::assertStringContainsString(
            'yii-debug-grid-summary-stat-4xx',
            $html,
            "'4xx' pill must carry the '4xx' status class."
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
            self::row(['tag' => 'abc']),
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
        $row = self::row([
            'time' => 1_700_000_000,
        ]);

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
            self::row(['time' => 0]),
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
            self::row(['url' => 'http://example.test/path']),
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

    /**
     * @param array<string, mixed> $overrides
     */
    private static function row(array $overrides = []): HistoryRow
    {
        return HistoryRow::fromSummary(
            RequestSummary::fromArray(
                [
                    'tag' => 'tag-1',
                    'url' => 'https://example.test/',
                    'ajax' => false,
                    'method' => 'GET',
                    'ip' => '127.0.0.1',
                    'time' => 1_700_000_000.0,
                    'statusCode' => 200,
                    'sqlCount' => 0,
                    'excessiveCallersCount' => 0,
                    'mailCount' => 0,
                    'mailFiles' => [],
                    'processingTime' => null,
                    'peakMemory' => null,
                    ...$overrides,
                ],
            ),
        );
    }
}
