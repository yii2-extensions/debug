<?php

declare(strict_types=1);

namespace yii\debug\tests\history;

use PHPForge\Debug\View\History\{HistoryCellRenderer, HistoryRow};
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;
use yii\debug\models\search\DebugSearch;
use yii\debug\tests\provider\HistoryPresentationProvider;
use yii\debug\widgets\history\HistoryRowRenderer;

/**
 * Characterizes the shared History presentation while retaining Yii2 critical-status policy.
 */
#[Group('history')]
final class HistoryPresentationCompatibilityTest extends TestCase
{
    /**
     * @param list<int> $criticalCodes
     * @param array<string, mixed> $expectedClass
     */
    #[DataProviderExternal(HistoryPresentationProvider::class, 'rowAttributes')]
    public function testRowAttributesPreserveDiagnosticsAndConfiguredCriticalCodes(
        int $statusCode,
        array $criticalCodes,
        bool $ajax,
        string $expectedStatus,
        string $expectedAjax,
        array $expectedClass,
    ): void {
        $row = self::row('<method>', '/search?q=<tag>&x="quote"', $statusCode, $ajax);

        $search = new DebugSearch();

        $search->criticalCodes = $criticalCodes;

        $expected = [
            ...$expectedClass,
            'data-yii-debug-tag' => '<capture>',
            'data-yii-debug-method' => '<method>',
            'data-yii-debug-url' => '/search?q=<tag>&x="quote"',
            'data-yii-debug-status' => $expectedStatus,
            'data-yii-debug-time' => 'custom clock',
            'data-yii-debug-ajax' => $expectedAjax,
        ];

        self::assertSame(
            $expected,
            HistoryRowRenderer::buildRowOptions($row, $search),
            'Yii2 must retain raw cursor values, attribute order, and its configured critical statuses.',
        );
        self::assertSame(
            $expected,
            HistoryCellRenderer::buildRowAttributes($row, $search->isCodeCritical($row->statusCode)),
            'Core must reproduce the same attributes when the adapter supplies the critical-status decision.',
        );
    }

    #[DataProviderExternal(HistoryPresentationProvider::class, 'textCells')]
    public function testTextCellsPreserveExactMarkup(
        string $method,
        string $url,
        bool $ajax,
        string $expectedMethod,
        string $expectedUrl,
        string $expectedAjax,
    ): void {
        $row = self::row($method, $url, 200, $ajax);

        self::assertSame(
            $expectedMethod,
            HistoryRowRenderer::renderMethodCell($row),
            'Yii2 method markup must not change.',
        );
        self::assertSame(
            $expectedMethod,
            HistoryCellRenderer::renderMethodCell($row),
            'Core method markup must match.',
        );
        self::assertSame(
            $expectedUrl,
            HistoryRowRenderer::renderUrlCell($row),
            'Yii2 URL escaping must not change.',
        );
        self::assertSame(
            $expectedUrl,
            HistoryCellRenderer::renderUrlCell($row),
            'Core URL escaping must match.',
        );
        self::assertSame(
            $expectedAjax,
            HistoryRowRenderer::renderAjaxCell($row),
            'Yii2 AJAX labels must not change.',
        );
        self::assertSame(
            $expectedAjax,
            HistoryCellRenderer::renderAjaxCell($row),
            'Core AJAX labels must match.',
        );
    }

    private static function row(string $method, string $url, int $statusCode, bool $ajax): HistoryRow
    {
        return new HistoryRow(
            tag: '<capture>',
            method: $method,
            url: $url,
            statusCode: $statusCode,
            time: 0.5,
            timeCompact: 'custom clock',
            processingTime: null,
            peakMemory: null,
            ip: '',
            sqlCount: 0,
            mailCount: 0,
            excessiveCallersCount: 0,
            ajax: $ajax,
        );
    }
}
