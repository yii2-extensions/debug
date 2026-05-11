<?php

declare(strict_types=1);

namespace yiiunit\debug;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\queue\{JobRecord, QueueCardRenderer, QueueSummary};

/**
 * Unit tests for {@see QueueCardRenderer} covering the typed Queue panel composition: summary header counts, status
 * pill variants, conditional tab strip, namespace splitting, avatar hue stability, and the meta footer.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('panel')]
#[Group('queue')]
final class QueueCardRendererTest extends TestCase
{
    public function testRenderAsyncHintListsDistinctAsyncDrivers(): void
    {
        $summary = new QueueSummary(
            [
                self::makeRecord(driverName: 'AMQP', isAsync: true),
                self::makeRecord(driverName: 'Redis', isAsync: true),
                self::makeRecord(driverName: 'AMQP', isAsync: true),
            ],
        );
        $hint = QueueCardRenderer::renderAsyncHint(
            $summary,
        );

        self::assertNotNull(
            $hint,
            'At least one async driver must produce a hint banner.',
        );

        $html = $hint->render();

        self::assertStringContainsString(
            'AMQP',
            $html,
            'AMQP must appear in the driver list.',
        );
        self::assertStringContainsString(
            'Redis',
            $html,
            'Redis must appear in the driver list.',
        );
        self::assertStringContainsString(
            'CLI',
            $html,
            'Hint must mention the CLI snapshots.',
        );
    }

    public function testRenderAsyncHintReturnsNullWhenAllRecordsAreSync(): void
    {
        $summary = new QueueSummary(
            [self::makeRecord(driverName: 'Sync', isAsync: false)],
        );

        self::assertNull(
            QueueCardRenderer::renderAsyncHint($summary),
            'All-sync summary must omit the hint banner.',
        );
    }

    public function testRenderItemEmitsCardWithClassAndStatusPill(): void
    {
        $record = self::makeRecord(jobClass: 'app\\jobs\\HelloJob', eventType: 'push');

        $html = QueueCardRenderer::renderItem($record)->render();

        self::assertStringContainsString(
            'class="yii-debug-queue-card"',
            $html,
            'Outer wrapper class must be present.',
        );
        self::assertStringContainsString(
            'HelloJob',
            $html,
            'Short class name must be rendered.'
        );
        self::assertStringContainsString(
            'app\\jobs\\',
            $html,
            'Namespace prefix must be rendered.'
        );
        self::assertStringContainsString(
            'Queued',
            $html,
            'Push event must show the `Queued` status pill.'
        );
    }

    public function testRenderItemMapsErrorToFailedStatusVariant(): void
    {
        $html = QueueCardRenderer::renderItem(
            self::makeRecord(eventType: 'error'),
        )->render();

        self::assertStringContainsString(
            'yii-debug-queue-status-failed',
            $html,
            "Error event must use the 'failed' status variant.",
        );
        self::assertStringContainsString(
            'Failed',
            $html,
            "Error event must show the 'Failed' label.",
        );
    }

    public function testRenderItemMapsExecToDoneStatusVariant(): void
    {
        $html = QueueCardRenderer::renderItem(
            self::makeRecord(eventType: 'exec'),
        )->render();

        self::assertStringContainsString(
            'yii-debug-queue-status-done',
            $html,
            "Exec event must use the 'done' status variant.",
        );
        self::assertStringContainsString(
            'Done',
            $html,
            "Exec event must show the 'Done' label.",
        );
    }

    public function testRenderItemOmitsComponentIdFromMetaStrip(): void
    {
        $html = QueueCardRenderer::renderItem(
            self::makeRecord(componentId: 'queueEmail'),
        )->render();

        self::assertStringNotContainsString(
            'data-field="component"',
            $html,
            'Component meta item must be hidden the sidebar/tab strip surfaces it instead.',
        );
        self::assertStringNotContainsString(
            'class="yii-debug-queue-meta"',
            $html,
            "'componentId' alone must not produce a meta strip.",
        );
    }

    public function testRenderItemOmitsDriverPillWhenDriverNameIsEmpty(): void
    {
        self::assertStringNotContainsString(
            'yii-debug-queue-driver',
            QueueCardRenderer::renderItem(self::makeRecord(driverName: ''))->render(),
            'Empty driver name must hide the driver pill.',
        );
    }

    public function testRenderItemOmitsMetaWhenNoOptionalFieldsPresent(): void
    {
        self::assertStringNotContainsString(
            'class="yii-debug-queue-meta"',
            QueueCardRenderer::renderItem(self::makeRecord())->render(),
            'No optional fields must omit the meta strip.',
        );
    }

    public function testRenderItemOmitsPayloadBlockWhenFieldsAreEmpty(): void
    {
        self::assertStringNotContainsString(
            'yii-debug-queue-payload',
            QueueCardRenderer::renderItem(self::makeRecord(payloadFields: []))->render(),
            'Empty payload fields must omit the block.',
        );
    }

    public function testRenderItemRendersAvatarHueDeterministicallyFromJobClass(): void
    {
        $first = QueueCardRenderer::renderItem(
            self::makeRecord(jobClass: 'app\\jobs\\Hello'),
        )->render();
        $second = QueueCardRenderer::renderItem(
            self::makeRecord(jobClass: 'app\\jobs\\Hello'),
        )->render();
        $third = QueueCardRenderer::renderItem(
            self::makeRecord(jobClass: 'app\\jobs\\World'),
        )->render();

        self::assertSame(
            self::extractHue($first),
            self::extractHue($second),
            'Same job class must produce the same hue.',
        );
        self::assertNotSame(
            self::extractHue($first),
            self::extractHue($third),
            'Different job classes must produce different hues.',
        );
    }

    public function testRenderItemRendersCollapsibleBlockForNestedObjects(): void
    {
        $html = QueueCardRenderer::renderItem(
            self::makeRecord(
                payloadFields: [
                    'inner' => [
                        '__class' => 'app\\models\\Inner',
                        'value' => 42,
                    ],
                ],
            ),
        )->render();

        self::assertStringContainsString(
            '<details',
            $html,
            "Nested object must render inside a collapsible '<details>'.",
        );
        self::assertStringContainsString(
            'Inner',
            $html,
            'Object short class name must be visible.',
        );
        self::assertStringContainsString(
            'app\\models',
            $html,
            'Object namespace must be rendered.'
        );
        self::assertStringContainsString(
            '>42<',
            $html,
            'Nested int value must be rendered.'
        );
    }

    public function testRenderItemRendersCollapsibleBlockForRegularArray(): void
    {
        $html = QueueCardRenderer::renderItem(
            self::makeRecord(
                payloadFields: [
                    'items' => [
                        'a',
                        'b',
                        'c',
                    ],
                ],
            ),
        )->render();

        self::assertStringContainsString(
            '<details',
            $html,
            "Nested array must render inside '<details>'."
        );
        self::assertStringContainsString(
            '>list<',
            $html,
            "List arrays must show the 'list' type label.",
        );
        self::assertStringContainsString(
            '(3)',
            $html,
            'Array length must be displayed.',
        );
    }

    public function testRenderItemRendersDriverPillWithName(): void
    {
        $html = QueueCardRenderer::renderItem(
            self::makeRecord(driverName: 'AMQP', isAsync: true),
        )->render();

        self::assertStringContainsString(
            'class="yii-debug-queue-driver yii-debug-queue-driver-is-async"',
            $html,
            "Async driver must use the 'is-async' modifier.",
        );
        self::assertStringContainsString(
            '>AMQP<',
            $html,
            'Driver name must be visible.',
        );
    }

    public function testRenderItemRendersErrorBlockOnlyWhenErrorMessagePresent(): void
    {
        $withError = QueueCardRenderer::renderItem(
            self::makeRecord(
                eventType: 'error',
                error: 'Boom: something failed',
            ),
        )->render();
        $withoutError = QueueCardRenderer::renderItem(
            self::makeRecord(),
        )->render();

        self::assertStringContainsString(
            'Boom: something failed',
            $withError,
            'Error message must be rendered.'
        );
        self::assertStringContainsString(
            'class="yii-debug-queue-error"',
            $withError,
            'Error block must carry the dedicated class.'
        );
        self::assertStringNotContainsString(
            'yii-debug-queue-error',
            $withoutError,
            'Records without error must omit the block.'
        );
    }

    public function testRenderItemRendersFallbackInitialAndHueWhenJobClassIsEmpty(): void
    {
        $html = QueueCardRenderer::renderItem(
            self::makeRecord(jobClass: ''),
        )->render();

        self::assertStringContainsString(
            '--queue-hue: 210',
            $html,
            "Empty class name must fall back to hue '210'.",
        );
        self::assertStringContainsString(
            '>?<',
            $html,
            "Empty class name must render '?' as the initial.",
        );
        self::assertStringContainsString(
            '(unknown)',
            $html,
            'Empty class name must show `(unknown)` as the title.',
        );
    }

    public function testRenderItemRendersMetaItemsWhenOptionalFieldsPresent(): void
    {
        $html = QueueCardRenderer::renderItem(
            self::makeRecord(
                jobId: 'msg-7',
                ttr: 30,
                delay: 5,
                priority: 10,
                attempt: 2,
                duration: 0.123,
            ),
        )->render();

        self::assertStringContainsString(
            'data-field="id"',
            $html,
            "'jobId' meta item must be rendered.",
        );
        self::assertStringContainsString(
            'msg-7',
            $html,
            "'jobId' value must be visible.",
        );
        self::assertStringContainsString(
            'data-field="ttr"',
            $html,
            "'ttr' meta item must be rendered.",
        );
        self::assertStringContainsString(
            '30s',
            $html,
            "'ttr' value must include the unit.",
        );
        self::assertStringContainsString(
            'data-field="delay"',
            $html,
            "'delay' meta item must be rendered.",
        );
        self::assertStringContainsString(
            'data-field="priority"',
            $html,
            "'priority' meta item must be rendered.",
        );
        self::assertStringContainsString(
            'data-field="attempt"',
            $html,
            "'attempt' meta item must be rendered.",
        );
        self::assertStringContainsString(
            '#2',
            $html,
            "'attempt' value must be prefixed with `#`.",
        );
        self::assertStringContainsString(
            'data-field="duration"',
            $html,
            "'duration' meta item must be rendered.",
        );
        self::assertStringContainsString(
            '123.0 ms',
            $html,
            "'duration' value must format as milliseconds.",
        );
    }

    public function testRenderItemRendersPayloadTreeWhenFieldsPresent(): void
    {
        $html = QueueCardRenderer::renderItem(
            self::makeRecord(
                payloadFields: [
                    'message' => 'first',
                    'priority' => 5,
                    'flag' => true,
                ],
            ),
        )->render();

        self::assertStringContainsString(
            'class="yii-debug-queue-payload"',
            $html,
            'Payload block must carry the dedicated class.',
        );
        self::assertStringContainsString(
            'class="yii-debug-queue-tree"',
            $html,
            'Tree wrapper must be present.',
        );
        self::assertStringContainsString(
            '"first"',
            $html,
            'String value must be rendered with quotes.',
        );
        self::assertStringContainsString(
            '>5<',
            $html,
            'Numeric value must be rendered.',
        );
        self::assertStringContainsString(
            '>true<',
            $html,
            'Boolean value must be rendered.',
        );
        self::assertStringContainsString(
            '>message<',
            $html,
            'String key must be visible.',
        );
        self::assertStringContainsString(
            '>priority<',
            $html,
            'Numeric key must be visible.',
        );
    }

    public function testRenderItemRendersSyncDriverPillWithSyncModifier(): void
    {
        self::assertStringContainsString(
            'yii-debug-queue-driver-is-sync',
            QueueCardRenderer::renderItem(self::makeRecord(driverName: 'Sync', isAsync: false))->render(),
            "Sync driver must use the 'is-sync' modifier.",
        );
    }

    public function testRenderItemRendersTypeLabelsForEachScalarKind(): void
    {
        $html = QueueCardRenderer::renderItem(
            self::makeRecord(
                payloadFields: [
                    'msg' => 'x',
                    'count' => 10,
                    'ratio' => 1.5,
                    'flag' => false,
                    'empty' => null,
                ],
            ),
        )->render();

        self::assertStringContainsString(
            '>string<',
            $html,
            'String type label must be present.',
        );
        self::assertStringContainsString(
            '>int<',
            $html,
            'Int type label must be present.',
        );
        self::assertStringContainsString(
            '>float<',
            $html,
            'Float type label must be present.',
        );
        self::assertStringContainsString(
            '>bool<',
            $html,
            'Bool type label must be present.',
        );
        self::assertStringContainsString(
            '>null<',
            $html,
            "'null' type label must be present.",
        );
    }

    public function testRenderItemSkipsZeroDelayMetaItem(): void
    {
        self::assertStringNotContainsString(
            'data-field="delay"',
            QueueCardRenderer::renderItem(self::makeRecord(jobId: 'msg-1', delay: 0))->render(),
            'Zero delay must be hidden only positive delays render.',
        );
    }

    public function testRenderItemTruncatesLongStringValuesAndKeepsFullValueInTitle(): void
    {
        $longValue = str_repeat('x', 200);

        $html = QueueCardRenderer::renderItem(
            self::makeRecord(payloadFields: ['data' => $longValue]),
        )->render();

        self::assertStringContainsString(
            '…',
            $html,
            'Long strings must be truncated with an ellipsis.',
        );
        self::assertStringContainsString(
            "title=\"{$longValue}\"",
            $html,
            'Full value must round-trip in the title attribute.',
        );
    }

    public function testRenderSummaryHeaderHidesExecutedCountWhenZero(): void
    {
        $summary = new QueueSummary(
            [self::makeRecord(eventType: 'push')],
        );

        self::assertStringNotContainsString(
            'executed',
            QueueCardRenderer::renderSummaryHeader($summary)->render(),
            'Zero executed count must be hidden.',
        );
    }

    public function testRenderSummaryHeaderShowsExecutedAndFailedCountsWhenNonZero(): void
    {
        $summary = new QueueSummary(
            [
                self::makeRecord(eventType: 'push'),
                self::makeRecord(eventType: 'exec'),
                self::makeRecord(eventType: 'error'),
            ],
        );
        $html = QueueCardRenderer::renderSummaryHeader(
            $summary,
        )->render();

        self::assertStringContainsString(
            'executed',
            $html,
            'Executed count must be present.',
        );
        self::assertStringContainsString(
            'failed',
            $html,
            'Failed count must be present.',
        );
        self::assertStringContainsString(
            'yii-debug-grid-summary-stat-danger',
            $html,
            'Failed count must use the danger variant.',
        );
    }

    public function testRenderSummaryHeaderShowsPushedCount(): void
    {
        $summary = new QueueSummary(
            [
                self::makeRecord(eventType: 'push'),
                self::makeRecord(eventType: 'push'),
            ],
        );
        $html = QueueCardRenderer::renderSummaryHeader(
            $summary,
        )->render();

        self::assertStringContainsString(
            'class="yii-debug-grid-summary"',
            $html,
            'Summary wrapper class must be present.',
        );
        self::assertStringContainsString(
            '<strong>2</strong> pushed',
            $html,
            'Pushed count must be visible.',
        );
    }

    /**
     * Extracts the queue avatar hue value from rendered HTML for hue-stability assertions.
     */
    private static function extractHue(string $html): int
    {
        if (preg_match('/--queue-hue: (\d+)/', $html, $m) === 1) {
            return (int) $m[1];
        }

        self::fail('No avatar hue found in rendered HTML.');
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
