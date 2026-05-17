<?php

declare(strict_types=1);

namespace yii\debug\tests\db;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\db\{DbQueryRenderer, QueryRow};
use yii\debug\panels\DbPanel;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see DbQueryRenderer} covering the typed cell renderers used by the queries grid (type pill,
 * timestamp formatting, duration formatting, rows fallback, query column with optional trace and EXPLAIN toggle).
 */
#[Group('panel')]
#[Group('db')]
final class DbQueryRendererTest extends TestCase
{
    public function testRenderDurationCellFormatsDurationToOneDecimalMillisecond(): void
    {
        self::assertSame(
            '12.5 ms',
            DbQueryRenderer::renderDurationCell(self::makeRow(duration: 12.5)),
            'Duration must keep one decimal.',
        );
        self::assertSame(
            '0.0 ms',
            DbQueryRenderer::renderDurationCell(self::makeRow(duration: 0.0)),
            "Zero duration must render as '0.0 ms'.",
        );
    }

    public function testRenderQueryCellEmitsExplainToggleWithBuiltUrl(): void
    {
        $html = DbQueryRenderer::renderQueryCell(
            self::makeRow(type: 'SELECT', seq: 7),
            $this->makePanel(DbPanel::class),
            true,
            self::makeUrlBuilder('request-tag-1'),
        );

        self::assertStringContainsString(
            'yii-debug-db-explain',
            $html,
            'Explain toggle wrapper must be present.',
        );
        self::assertStringContainsString(
            'seq=7',
            $html,
            'Explain URL must carry the row sequence.',
        );
        self::assertStringContainsString(
            'tag=request-tag-1',
            $html,
            'Explain URL must carry the request tag.',
        );
        self::assertStringContainsString(
            'aria-label="Toggle EXPLAIN output"',
            $html,
            'Toggle must expose an accessible name.',
        );
    }

    public function testRenderQueryCellEmitsTraceListWhenTracePresent(): void
    {
        $html = DbQueryRenderer::renderQueryCell(
            self::makeRow(trace: [['file' => '/app/User.php', 'line' => 42]]),
            $this->makePanel(DbPanel::class),
            false,
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            'class="yii-debug-trace"',
            $html,
            'Trace list must carry the dedicated class.',
        );
        self::assertStringContainsString(
            'User.php',
            $html,
            'Trace list must render frame metadata.',
        );
    }

    public function testRenderQueryCellEscapesQueryContent(): void
    {
        $html = DbQueryRenderer::renderQueryCell(
            self::makeRow(query: '<script>'),
            $this->makePanel(DbPanel::class),
            false,
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            '&lt;script&gt;',
            $html,
            'Query content must be HTML-escaped.',
        );
        self::assertStringNotContainsString(
            '<script>',
            $html,
            'Raw HTML must not leak into the output.',
        );
    }

    public function testRenderQueryCellOmitsExplainToggleWhenHasExplainIsFalse(): void
    {
        $html = DbQueryRenderer::renderQueryCell(
            self::makeRow(type: 'SELECT'),
            $this->makePanel(DbPanel::class),
            false,
            self::makeUrlBuilder(),
        );

        self::assertStringNotContainsString(
            'yii-debug-db-explain',
            $html,
            'Explain toggle must be hidden when the driver does not support EXPLAIN.',
        );
    }

    public function testRenderQueryCellOmitsExplainToggleWhenTypeIsNotExplainable(): void
    {
        $html = DbQueryRenderer::renderQueryCell(
            self::makeRow(type: 'PRAGMA'),
            $this->makePanel(DbPanel::class),
            true,
            self::makeUrlBuilder(),
        );

        self::assertStringNotContainsString(
            'yii-debug-db-explain',
            $html,
            'PRAGMA must not produce an explain toggle.',
        );
    }

    public function testRenderQueryCellOmitsTraceListWhenTraceEmpty(): void
    {
        $html = DbQueryRenderer::renderQueryCell(
            self::makeRow(trace: []),
            $this->makePanel(DbPanel::class),
            false,
            self::makeUrlBuilder(),
        );

        self::assertStringNotContainsString(
            'yii-debug-trace',
            $html,
            'Empty trace must omit the trace list entirely.',
        );
    }

    public function testRenderQueryCellWrapsSqlInTheDebugSqlContainer(): void
    {
        $html = DbQueryRenderer::renderQueryCell(
            self::makeRow(query: 'SELECT 1'),
            $this->makePanel(DbPanel::class),
            false,
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            'class="yii-debug-db-sql"',
            $html,
            'SQL must be wrapped in the dedicated container.',
        );
        self::assertStringContainsString(
            'SELECT 1',
            $html,
            'SQL text must be rendered.',
        );
    }

    public function testRenderRowsCellShowsEnDashWhenRowsAreUnknown(): void
    {
        self::assertSame(
            '–',
            DbQueryRenderer::renderRowsCell(self::makeRow(rows: null)),
            'Missing rows must render the en-dash placeholder.',
        );
    }

    public function testRenderRowsCellShowsRowOrRowsBasedOnCount(): void
    {
        self::assertSame(
            '1 row',
            DbQueryRenderer::renderRowsCell(self::makeRow(rows: 1)),
            'Single row must use the singular noun.'
        );
        self::assertSame(
            '5 rows',
            DbQueryRenderer::renderRowsCell(self::makeRow(rows: 5)),
            'Multiple rows must use the plural noun.'
        );
        self::assertSame(
            '0 rows',
            DbQueryRenderer::renderRowsCell(self::makeRow(rows: 0)),
            'Zero rows must still pluralize.'
        );
    }

    public function testRenderTimeCellFormatsMillisecondTimestampAsHmsWithMillis(): void
    {
        $timestampMs = 1_700_000_000_789.0;

        $expected = date('H:i:s.', 1_700_000_000) . '789';

        $html = DbQueryRenderer::renderTimeCell(self::makeRow(timestamp: $timestampMs));

        self::assertSame(
            $expected,
            $html,
            "Timestamp must format as 'H:i:s.mmm'.",
        );
    }

    public function testRenderTypeCellMapsInsertToSuccessAndDeleteToDanger(): void
    {
        self::assertStringContainsString(
            'yii-debug-db-type-success',
            DbQueryRenderer::renderTypeCell(self::makeRow(type: 'INSERT')),
            "INSERT must map to the 'success' variant.",
        );
        self::assertStringContainsString(
            'yii-debug-db-type-danger',
            DbQueryRenderer::renderTypeCell(self::makeRow(type: 'DELETE')),
            "DELETE must map to the 'danger' variant.",
        );
    }

    public function testRenderTypeCellWrapsTypeInAColoredBadge(): void
    {
        $html = DbQueryRenderer::renderTypeCell(self::makeRow(type: 'SELECT'));

        self::assertStringContainsString(
            'class="yii-debug-db-type yii-debug-db-type-info"',
            $html,
            "SELECT must use the 'info' variant.",
        );
        self::assertStringContainsString(
            '>SELECT<',
            $html,
            'Type label must be rendered as the badge content.',
        );
    }

    /**
     * @param list<array<string, mixed>> $trace
     */
    private static function makeRow(
        string $type = 'SELECT',
        string $query = 'SELECT 1',
        float $duration = 1.0,
        array $trace = [],
        string $traceHash = '',
        float $timestamp = 0.0,
        int $seq = 0,
        int $duplicate = 1,
        int|null $rows = null,
    ): QueryRow {
        return new QueryRow(
            type: $type,
            query: $query,
            duration: $duration,
            trace: $trace,
            traceHash: $traceHash,
            timestamp: $timestamp,
            seq: $seq,
            duplicate: $duplicate,
            rows: $rows,
        );
    }

    /**
     * Builds a deterministic explain-URL builder so tests can assert the rendered href without needing an active
     * controller context.
     *
     * @return callable(int): string
     */
    private static function makeUrlBuilder(string $tag = 'tag'): callable
    {
        return static fn(int $seq): string => "/debug/db-explain?seq={$seq}&tag={$tag}";
    }
}
