<?php

declare(strict_types=1);

namespace yiiunit\debug;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\queue\{JobRecord, QueueGridRenderer};

/**
 * Unit tests for {@see QueueGridRenderer} covering the per-cell HTML output that drives the Queue panel grid view.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('panel')]
#[Group('queue')]
final class QueueGridRendererTest extends TestCase
{
    public function testRenderAttemptCellFormatsAsHashedNumber(): void
    {
        self::assertSame(
            '#3',
            QueueGridRenderer::renderAttemptCell(self::makeRecord(attempt: 3)),
            "Non-zero attempt must render as '#N'.",
        );
    }

    public function testRenderAttemptCellReturnsDashWhenAttemptIsNullOrZero(): void
    {
        self::assertSame(
            '—',
            QueueGridRenderer::renderAttemptCell(self::makeRecord(attempt: null)),
            "'null' attempt must yield '—'.",
        );

        self::assertSame(
            '—',
            QueueGridRenderer::renderAttemptCell(self::makeRecord(attempt: 0)),
            "Zero attempt must yield '—'.",
        );
    }

    public function testRenderComponentCellReturnsRawComponentId(): void
    {
        self::assertSame(
            'queueRedis',
            QueueGridRenderer::renderComponentCell(self::makeRecord(componentId: 'queueRedis')),
            'Component cell must echo the component id verbatim.',
        );
    }

    public function testRenderDriverCellAddsAsyncModifier(): void
    {
        $html = QueueGridRenderer::renderDriverCell(
            self::makeRecord(driverName: 'Redis', isAsync: true),
        );

        self::assertStringContainsString(
            'yii-debug-queue-driver-is-async',
            $html,
            "Async drivers must carry the 'is-async' modifier.",
        );
        self::assertStringContainsString(
            'Redis',
            $html,
            'Driver label must appear in the cell.',
        );
    }

    public function testRenderDriverCellAddsSyncModifierWhenInProcess(): void
    {
        self::assertStringContainsString(
            'yii-debug-queue-driver-is-sync',
            QueueGridRenderer::renderDriverCell(self::makeRecord(driverName: 'Sync', isAsync: false)),
            "Sync drivers must carry the 'is-sync' modifier.",
        );
    }

    public function testRenderDriverCellReturnsEmptyWhenDriverNameIsMissing(): void
    {
        self::assertSame(
            '',
            QueueGridRenderer::renderDriverCell(self::makeRecord(driverName: '')),
            'Empty driver name must yield an empty cell.',
        );
    }

    public function testRenderDurationCellFormatsMilliseconds(): void
    {
        self::assertSame(
            '12.3 ms',
            QueueGridRenderer::renderDurationCell(self::makeRecord(duration: 0.0123)),
            "Seconds must be formatted as 'XX.X ms'.",
        );
    }

    public function testRenderDurationCellReturnsDashWhenDurationIsNull(): void
    {
        self::assertSame(
            '—',
            QueueGridRenderer::renderDurationCell(self::makeRecord(duration: null)),
            "Missing duration must yield '—'.",
        );
    }

    public function testRenderIdCellReturnsDashWhenJobIdIsEmpty(): void
    {
        self::assertSame(
            '—',
            QueueGridRenderer::renderIdCell(self::makeRecord(jobId: '')),
            "Empty job id must yield '—' to keep the column readable.",
        );
    }

    public function testRenderIdCellWrapsJobIdInTagLinkSpan(): void
    {
        $html = QueueGridRenderer::renderIdCell(
            self::makeRecord(jobId: '69ffbbf2a6830'),
        );

        self::assertStringContainsString(
            'class="yii-debug-tag-link"',
            $html,
            'Id must reuse the History tag-link styling.',
        );
        self::assertStringContainsString(
            '69ffbbf2a6830',
            $html,
            'Cell must render the raw job id verbatim.',
        );
    }

    public function testRenderJobCellSplitsFqcnAndWiresHref(): void
    {
        $html = QueueGridRenderer::renderJobCell(
            self::makeRecord(jobClass: 'app\\jobs\\HelloJob'),
            '/debug/queue?seq=2',
        );

        self::assertStringContainsString(
            '<strong>HelloJob</strong>',
            $html,
            'Short class name must render in bold inside the link.',
        );
        self::assertStringContainsString(
            'app\\jobs',
            $html,
            'Namespace prefix must appear under the link.',
        );
        self::assertStringContainsString(
            'href="/debug/queue?seq=2"',
            $html,
            'Detail href must be wired to the row link.',
        );
    }

    public function testRenderStatusCellRendersFailedVariantForErrorEvents(): void
    {
        $html = QueueGridRenderer::renderStatusCell(self::makeRecord(eventType: 'error'));

        self::assertStringContainsString(
            'yii-debug-queue-status-failed',
            $html,
            "Error events must produce the 'failed' modifier.",
        );
        self::assertStringContainsString(
            'Failed',
            $html,
            "Error events must show the 'Failed' label.",
        );
    }

    public function testRenderStatusCellRendersQueuedVariantForPushEvents(): void
    {
        $html = QueueGridRenderer::renderStatusCell(
            self::makeRecord(eventType: 'push'),
        );

        self::assertStringContainsString(
            'yii-debug-queue-status-queued',
            $html,
            "Push events must produce the 'queued' modifier.",
        );
        self::assertStringContainsString(
            'Queued',
            $html,
            "Push events must show the 'Queued' label.",
        );
    }

    public function testRenderTimeCellFormatsMicrotimeAsHmsWithMilliseconds(): void
    {
        // 2024-01-01 12:34:56.789 UTC = 1704112496.789. We avoid asserting exact wall-clock time (depends on TZ), so we
        // only assert the format shape: 8+1+3 chars, dot in position 8, three trailing digits.
        $html = QueueGridRenderer::renderTimeCell(self::makeRecord(time: 1_704_112_496.789));

        self::assertMatchesRegularExpression(
            '/^\d{2}:\d{2}:\d{2}\.\d{3}$/',
            $html,
            "Time cell must follow 'HH:MM:SS.mmm'.",
        );
    }

    public function testRenderTtrCellAppendsSecondsSuffix(): void
    {
        self::assertSame(
            '300s',
            QueueGridRenderer::renderTtrCell(self::makeRecord(ttr: 300)),
            "Non-zero TTR must render as 'Ns'.",
        );
    }

    public function testRenderTtrCellReturnsDashWhenTtrIsNullOrZero(): void
    {
        self::assertSame(
            '—',
            QueueGridRenderer::renderTtrCell(self::makeRecord(ttr: null)),
            "Null TTR must yield '—'.",
        );

        self::assertSame(
            '—',
            QueueGridRenderer::renderTtrCell(self::makeRecord(ttr: 0)),
            "Zero TTR must yield '—'.",
        );
    }

    /**
     * @param array<string, mixed> $payloadFields
     */
    private static function makeRecord(
        string $eventType = 'push',
        string $componentId = 'queue',
        string $driverName = 'Sync',
        string $driverClass = 'yii\\queue\\sync\\Queue',
        bool $isAsync = false,
        string $jobClass = 'app\\jobs\\HelloJob',
        array $payloadFields = [],
        float $time = 0.0,
        string $jobId = '',
        int|null $ttr = null,
        int|null $delay = null,
        int|null $priority = null,
        int|null $attempt = null,
        float|null $duration = null,
        string $error = '',
    ): JobRecord {
        return new JobRecord(
            eventType: $eventType,
            componentId: $componentId,
            driverName: $driverName,
            driverClass: $driverClass,
            isAsync: $isAsync,
            jobClass: $jobClass,
            payloadFields: $payloadFields,
            time: $time,
            jobId: $jobId,
            ttr: $ttr,
            delay: $delay,
            priority: $priority,
            attempt: $attempt,
            duration: $duration,
            error: $error,
        );
    }
}
