<?php

declare(strict_types=1);

namespace yii\debug\tests\queue;

use PHPForge\Debug\Panel\Queue\JobRecord;
use PHPForge\Debug\Panel\Queue\QueueSnapshot;
use PHPUnit\Framework\Attributes\Group;
use stdClass;
use Yii;
use yii\debug\panels\QueuePanel;
use yii\debug\tests\support\TestCase;

use function class_exists;

/**
 * Unit tests for {@see QueuePanel} covering snapshot hydration, the toolbar items, the `queue-job` action registration,
 * and the queue base-class detection.
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

    public function testComponentMatchesQueueBaseHandlesStringConfigArrayAndObjectInputs(): void
    {
        $panel = $this->makePanel(
            QueuePanel::class,
        );

        self::assertFalse(
            $this->invoke(
                $panel,
                'componentMatchesQueueBase',
                [new stdClass()]
            ),
            'Foreign object must not match the queue base class.',
        );
        self::assertFalse(
            $this->invoke(
                $panel,
                'componentMatchesQueueBase',
                ['some\\Unrelated\\Class']
            ),
            'Unrelated class string must not match.',
        );
        self::assertFalse(
            $this->invoke(
                $panel,
                'componentMatchesQueueBase',
                [['class' => 'some\\Unrelated\\Class']]
            ),
            'Config array with unrelated class must not match.',
        );
        self::assertFalse(
            $this->invoke(
                $panel,
                'componentMatchesQueueBase',
                [['no-class-key' => 'foo']]
            ),
            "Config array without 'class' key must not match.",
        );
        self::assertFalse(
            $this->invoke(
                $panel,
                'componentMatchesQueueBase',
                [42]
            ),
            'Non-class scalar must not match.',
        );
    }

    public function testComponentMatchesQueueBaseReturnsSubclassResultForObjects(): void
    {
        if (!class_exists('yii\\queue\\Queue', false)) {
            eval('namespace yii\\queue; abstract class Queue extends \\yii\\base\\Component {}');
        }

        $component = eval('return new class extends \\yii\\queue\\Queue {};');

        self::assertIsObject(
            $component,
            'Test component must be an object.',
        );

        $panel = $this->makePanel(
            QueuePanel::class,
        );

        self::assertTrue(
            $this->invoke($panel, 'componentMatchesQueueBase', [$component]),
            'Subclass of queue base must match.',
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

        self::assertNotEmpty(
            $panel->getDetail(),
            'Detail view must produce markup.',
        );
    }

    public function testGetNameAndIconAndIsEnabled(): void
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
            'Panel must always be enabled.',
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

    public function testGetToolbarItemsEmitsCountChipWhenComponentConfiguredAndNoRecords(): void
    {
        $panel = $this->makePanel(
            QueuePanel::class,
        );

        Yii::$app->set(
            'queue',
            ['class' => 'yii\\queue\\Queue'],
        );

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Toolbar items must be a list.',
        );
        self::assertCount(
            1,
            $items,
            'No events means a single count chip.',
        );

        $first = $items[0] ?? self::fail('Expected count chip.');

        self::assertIsArray(
            $first,
            'Count chip must be an array.',
        );
        self::assertSame(
            0,
            $first['value'] ?? null,
            "Empty roster must report '0' events.",
        );
    }

    public function testGetToolbarItemsReturnsEmptyArrayWhenNoComponentAndNoRecords(): void
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
            'No queue component and no records must skip the toolbar.',
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
