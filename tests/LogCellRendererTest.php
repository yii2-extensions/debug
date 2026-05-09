<?php

declare(strict_types=1);

namespace yiiunit\debug;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\Module;
use yii\debug\panels\log\{LogCellRenderer, LogRow};
use yii\debug\panels\LogPanel;
use yii\log\Logger;

/**
 * Unit tests for {@see LogCellRenderer} covering the typed cell renderers used by the logs grid (time, level,
 * time-since-previous navigation, message + trace, row options).
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('panel')]
#[Group('log')]
final class LogCellRendererTest extends TestCase
{
    public function testBuildRowOptionsAttachesAnchorIdAndSeverityClass(): void
    {
        $options = LogCellRenderer::buildRowOptions(
            self::makeRow(id: 7, level: Logger::LEVEL_ERROR),
        );

        self::assertSame(
            'log-7',
            $options['id'] ?? null,
            "Row id must be 'log-{N}'.",
        );
        self::assertSame(
            'yii-debug-row--danger',
            $options['class'] ?? null,
            "Error level must carry the danger class.",
        );
    }

    public function testBuildRowOptionsMapsWarningAndInfoToTheirVariantClasses(): void
    {
        self::assertSame(
            'yii-debug-row--warning',
            LogCellRenderer::buildRowOptions(self::makeRow(level: Logger::LEVEL_WARNING))['class'] ?? null,
            'Warning level must map to the warning class.',
        );
        self::assertSame(
            'yii-debug-row--info',
            LogCellRenderer::buildRowOptions(self::makeRow(level: Logger::LEVEL_INFO))['class'] ?? null,
            'Info level must map to the info class.',
        );
    }

    public function testBuildRowOptionsOmitsClassForLevelsWithoutVariantMapping(): void
    {
        $options = LogCellRenderer::buildRowOptions(
            self::makeRow(id: 3, level: Logger::LEVEL_TRACE),
        );

        self::assertSame(
            'log-3',
            $options['id'] ?? null,
            "Row id must be 'log-{N}'.",
        );
        self::assertArrayNotHasKey(
            'class',
            $options,
            'Trace level must not attach a row class.',
        );
    }

    public function testRenderLevelCellMapsLoggerLevelConstantsToNames(): void
    {
        self::assertSame(
            Logger::getLevelName(Logger::LEVEL_ERROR),
            LogCellRenderer::renderLevelCell(self::makeRow(level: Logger::LEVEL_ERROR)),
            'Error level must map to its canonical name.',
        );
        self::assertSame(
            Logger::getLevelName(Logger::LEVEL_WARNING),
            LogCellRenderer::renderLevelCell(self::makeRow(level: Logger::LEVEL_WARNING)),
            'Warning level must map to its canonical name.',
        );
    }

    public function testRenderMessageCellAppendsTraceListWhenTraceHasFrames(): void
    {
        $html = LogCellRenderer::renderMessageCell(
            self::makeRow(
                message: 'Something happened',
                trace: [['file' => '/app/User.php', 'line' => 42]],
            ),
            self::makePanel(),
        );

        self::assertStringContainsString(
            'Something happened',
            $html,
            'Message text must be present.',
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

    public function testRenderMessageCellEscapesPlainMessageWhenTraceIsEmpty(): void
    {
        $html = LogCellRenderer::renderMessageCell(
            self::makeRow(message: '<script>alert(1)</script>'),
            self::makePanel(),
        );

        self::assertStringContainsString(
            '&lt;script&gt;',
            $html,
            'Message must be HTML-escaped.',
        );
        self::assertStringNotContainsString(
            'yii-debug-trace',
            $html,
            'Empty trace must omit the trace list.',
        );
    }

    public function testRenderTimeCellFormatsMillisecondTimestampAsHmsWithMillis(): void
    {
        $expected = date('H:i:s', 1_700_000_000) . '.789';

        $html = LogCellRenderer::renderTimeCell(
            self::makeRow(time: 1_700_000_000_789.0),
        );

        self::assertSame(
            $expected,
            $html,
            "Timestamp must format as 'H:i:s.mmm'.",
        );
    }

    public function testRenderTimeSincePreviousCellEmitsAbsoluteDiffWithUnitsAndArrows(): void
    {
        $html = LogCellRenderer::renderTimeSincePreviousCell(
            self::makeRow(
                time: 1_700_000_001_500.0,
                timeOfPrevious: 1_700_000_000_000.0,
                idOfPrevious: 6,
                idOfNext: 8,
            ),
        );

        self::assertStringContainsString(
            'class="yii-debug-since-previous"',
            $html,
            'Wrapper class must be present.',
        );
        self::assertStringContainsString(
            '1s',
            $html,
            'Diff must include the seconds component.',
        );
        self::assertStringContainsString(
            '500ms',
            $html,
            'Diff must include the milliseconds component.',
        );
        self::assertStringContainsString(
            'href="#log-6"',
            $html,
            'Previous arrow must link to the previous row anchor.',
        );
        self::assertStringContainsString(
            'href="#log-8"',
            $html,
            'Next arrow must link to the next row anchor.',
        );
    }

    public function testRenderTimeSincePreviousCellRendersDisabledArrowsAtBoundaries(): void
    {
        $html = LogCellRenderer::renderTimeSincePreviousCell(
            self::makeRow(
                time: 1_700_000_000_500.0,
                timeOfPrevious: 1_700_000_000_500.0,
                idOfPrevious: null,
                idOfNext: null,
            )
        );

        self::assertStringContainsString(
            'is-disabled',
            $html,
            'Boundary rows must render disabled arrows.',
        );
        self::assertStringNotContainsString(
            'href="#log-',
            $html,
            'Disabled arrows must not contain anchor hrefs.',
        );
        self::assertStringContainsString(
            '0ms',
            $html,
            "Equal timestamps must render '0ms'.",
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockWebApplication();
    }

    private static function makePanel(): LogPanel
    {
        return new LogPanel(
            [
                'id' => 'log',
                'tag' => 'tag',
                'module' => new Module('debug'),
            ],
        );
    }

    /**
     * @param list<array<string, mixed>> $trace
     */
    private static function makeRow(
        int $id = 1,
        string $message = 'msg',
        int $level = Logger::LEVEL_INFO,
        string $category = 'app',
        float $time = 0.0,
        float $timeOfPrevious = 0.0,
        int|null $idOfPrevious = null,
        int|null $idOfNext = null,
        array $trace = [],
    ): LogRow {
        return new LogRow(
            id: $id,
            message: $message,
            level: $level,
            category: $category,
            time: $time,
            timeOfPrevious: $timeOfPrevious,
            idOfPrevious: $idOfPrevious,
            idOfNext: $idOfNext,
            trace: $trace,
        );
    }
}
