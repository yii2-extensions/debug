<?php

declare(strict_types=1);

namespace yii\debug\tests\queue;

use PHPForge\Debug\Panel\Queue\JobRecord;
use PHPForge\Debug\Panel\Queue\QueueSnapshot;
use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\QueuePanel;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see QueuePanel} covering snapshot hydration, the toolbar items, and the `queue-job` action
 * registration.
 */
#[Group('panel')]
#[Group('queue')]
final class QueuePanelTest extends TestCase
{
    public function testArrayRecordsReturnsEmptyWhenDataIsNotArray(): void
    {
        $panel = $this->makePanel(
            QueuePanel::class,
        );

        self::assertSame(
            [],
            $panel->getRecords(),
            "Non-array data must collapse to '[]'.",
        );
    }

    public function testArrayRecordsReturnsEmptyWhenRecordsKeyMissing(): void
    {
        $panel = $this->makePanel(
            QueuePanel::class,
        );

        self::assertSame(
            [],
            $panel->getRecords(),
            "Missing 'records' key must collapse to '[]'.",
        );
    }

    public function testGetDetailRendersEmptyStateWhenNoRecords(): void
    {
        $panel = $this->makePanel(
            QueuePanel::class,
        );

        $this->hydratePanel(
            $panel,
            QueueSnapshot::capture([]),
        );

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'No jobs queued in this request',
            $html,
            'Empty queue panel must surface the empty-state hint.',
        );
        self::assertStringContainsString(
            'yii-debug-grid-summary',
            $html,
            'Summary strip must render alongside the card.',
        );
    }

    public function testGetDetailRendersExecutedAndErrorStatsAndAsyncHint(): void
    {
        $panel = $this->makePanel(
            QueuePanel::class,
        );

        $this->hydratePanel(
            $panel,
            QueueSnapshot::capture(
                [
                    [
                        'eventType' => JobRecord::TYPE_PUSH,
                        'componentId' => 'queue',
                        'driverName' => 'Database',
                        'driverClass' => 'yii\\queue\\db\\Queue',
                        'isAsync' => true,
                        'jobClass' => 'App\\Job',
                        'payloadFields' => [],
                        'time' => 0.0,
                        'jobId' => 'job-1',
                        'ttr' => null,
                        'delay' => null,
                        'priority' => null,
                        'attempt' => null,
                        'duration' => null,
                        'error' => '',
                    ],
                    [
                        'eventType' => JobRecord::TYPE_EXEC,
                        'componentId' => 'queue',
                        'driverName' => 'Database',
                        'driverClass' => 'yii\\queue\\db\\Queue',
                        'isAsync' => true,
                        'jobClass' => 'App\\Job',
                        'payloadFields' => [],
                        'time' => 0.0,
                        'jobId' => 'job-1',
                        'ttr' => null,
                        'delay' => null,
                        'priority' => null,
                        'attempt' => 1,
                        'duration' => 0.05,
                        'error' => '',
                    ],
                    [
                        'eventType' => JobRecord::TYPE_ERROR,
                        'componentId' => 'queue',
                        'driverName' => 'Database',
                        'driverClass' => 'yii\\queue\\db\\Queue',
                        'isAsync' => true,
                        'jobClass' => 'App\\Job',
                        'payloadFields' => [],
                        'time' => 0.0,
                        'jobId' => 'job-1',
                        'ttr' => null,
                        'delay' => null,
                        'priority' => null,
                        'attempt' => 1,
                        'duration' => 0.05,
                        'error' => 'job failed',
                    ],
                ],
            ),
        );

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'executed',
            $html,
            'Executed counter must surface.',
        );
        self::assertStringContainsString(
            'failed',
            $html,
            'Failed counter must surface.',
        );
    }

    public function testGetDetailRendersWithCapturedRecords(): void
    {
        $panel = $this->makePanel(
            QueuePanel::class,
        );

        $this->hydratePanel(
            $panel,
            QueueSnapshot::capture(
                [
                    [
                        'eventType' => JobRecord::TYPE_PUSH,
                        'componentId' => 'queue',
                        'driverName' => 'Sync',
                        'driverClass' => 'yii\\queue\\sync\\Queue',
                        'isAsync' => false,
                        'jobClass' => 'App\\Job',
                        'payloadFields' => [],
                        'time' => 0.0,
                        'jobId' => 'job-1',
                        'ttr' => null,
                        'delay' => null,
                        'priority' => null,
                        'attempt' => null,
                        'duration' => null,
                        'error' => '',
                    ],
                ],
            ),
        );

        $detail = $panel->getDetail();

        self::assertNotEmpty(
            $detail,
            'Detail view must produce markup.',
        );
        self::assertStringContainsString(
            'class="yii-debug-queue-grid-job-link" href="/index.php?r=debug%2Fqueue-job',
            $detail,
            'The job name must remain a native link to its detail page.',
        );
        self::assertStringNotContainsString(
            'data-href=',
            $detail,
            'Rows must not advertise an unimplemented click target.',
        );
    }

    public function testGetNameAndIconAndKeepsRendererEnabled(): void
    {
        $panel = $this->makePanel(
            QueuePanel::class,
        );

        self::assertSame(
            'Queue',
            $panel->getName(),
            "Display name must be 'Queue'.",
        );
        self::assertSame(
            'queue',
            $panel->getToolbarIcon(),
            "Icon key must be 'queue'."
        );
        self::assertTrue(
            $panel->isEnabled(),
            'The renderer must defer provider availability gating to the module registration boundary.',
        );
    }

    public function testGetToolbarItemsEmitsCountAndDangerChipWhenErrorsCaptured(): void
    {
        $panel = $this->makePanel(
            QueuePanel::class,
        );

        $records = [];

        for ($i = 0; $i < 3; $i++) {
            $records[] = $this->makeRecord(['eventType' => $i === 2 ? JobRecord::TYPE_ERROR : JobRecord::TYPE_PUSH]);
        }

        $this->hydratePanel(
            $panel,
            QueueSnapshot::capture($records),
        );

        self::assertSame(
            [
                ['value' => 3],
                ['label' => 'Errors', 'status' => 'danger', 'value' => 1],
            ],
            $this->invoke($panel, 'getToolbarItems'),
            'Toolbar items must emit a count chip and a danger chip when errors are present.'
        );
    }

    public function testGetToolbarItemsReturnsEmptyArrayWhenNoRecordsWereCaptured(): void
    {
        $panel = $this->makePanel(
            QueuePanel::class,
        );

        self::assertSame(
            [],
            $this->invoke(
                $panel,
                'getToolbarItems',
            ),
            'No captured queue events must skip the toolbar.',
        );
    }

    public function testInitRegistersTheQueueJobAction(): void
    {
        $panel = $this->makePanel(
            QueuePanel::class,
        );

        self::assertArrayHasKey(
            'queue-job',
            $panel->actions,
            "Init must register the 'queue-job' action.",
        );
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function makeRecord(array $overrides = []): array
    {
        return $overrides + [
            'eventType' => JobRecord::TYPE_PUSH,
            'componentId' => 'queue',
            'driverName' => 'Sync',
            'driverClass' => '',
            'isAsync' => false,
            'jobClass' => '',
            'payloadFields' => [],
            'time' => 0.0,
            'jobId' => '',
            'ttr' => null,
            'delay' => null,
            'priority' => null,
            'attempt' => null,
            'duration' => null,
            'error' => '',
        ];
    }
}
