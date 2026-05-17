<?php

declare(strict_types=1);

namespace yii\debug\tests\queue;

use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use stdClass;
use Yii;
use yii\base\{Component, Event};
use yii\debug\panels\queue\JobRecord;
use yii\debug\panels\QueuePanel;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see QueuePanel} covering the queue lifecycle capture, the saved-payload narrowing, the toolbar
 * sitems, and the helpers that resolve component ids and queue base classes.
 */
#[Group('panel')]
#[Group('queue')]
final class QueuePanelTest extends TestCase
{
    public function testArrayRecordsDropsNonArrayEntriesAndNonStringKeys(): void
    {
        $panel = $this->makePanel(QueuePanel::class);

        $this->setInaccessibleProperty(
            $panel,
            'data',
            [
                'records' => [
                    [
                        'eventType' => 'push',
                        0 => 'dropped-int-key',
                    ],
                    'invalid-entry',
                    ['eventType' => 'exec'],
                ],
            ],
        );

        $records = $this->invoke(
            $panel,
            'arrayRecords',
        );

        self::assertIsArray(
            $records,
            'Records must be a list.',
        );
        self::assertCount(
            2,
            $records,
            'Non-array entries must be dropped.',
        );

        $first = $records[0] ?? self::fail('Expected one record.');

        self::assertIsArray(
            $first,
            'Record must be an array.',
        );
        self::assertArrayNotHasKey(
            0,
            $first,
            'Int keys must be filtered out.',
        );
    }

    public function testArrayRecordsReturnsEmptyWhenDataIsNotArray(): void
    {
        $panel = $this->makePanel(QueuePanel::class);

        $this->setInaccessibleProperty(
            $panel,
            'data',
            'corrupt',
        );

        self::assertSame(
            [],
            $this->invoke(
                $panel,
                'arrayRecords',
            ),
            "Non-array data must collapse to '[]'.",
        );
    }

    public function testArrayRecordsReturnsEmptyWhenRecordsKeyMissing(): void
    {
        $panel = $this->makePanel(QueuePanel::class);

        $this->setInaccessibleProperty(
            $panel,
            'data',
            ['other' => 'value'],
        );

        self::assertSame(
            [],
            $this->invoke(
                $panel,
                'arrayRecords',
            ),
            "Missing 'records' key must collapse to '[]'.",
        );
    }

    public function testComponentIdOfReturnsCachedRegisteredId(): void
    {
        $panel = $this->makePanel(QueuePanel::class);

        $queueComponent = new Component();

        Yii::$app->set('myQueue', $queueComponent);
        Yii::$app->get('myQueue'); // force instantiation so `getComponents(false)` exposes it

        $event = new Event();

        $event->sender = $queueComponent;

        self::assertSame(
            'myQueue',
            $this->invoke(
                $panel,
                'componentIdOf',
                [$event]
            ),
            'Component id must round-trip from the registered name.',
        );
        self::assertSame(
            'myQueue',
            $this->invoke(
                $panel,
                'componentIdOf',
                [$event]
            ),
            'Cached lookup must return the same id on repeat.',
        );
    }

    public function testComponentIdOfReturnsEmptyForNonObjectSender(): void
    {
        $panel = $this->makePanel(QueuePanel::class);

        $event = new Event();

        self::assertSame(
            '',
            $this->invoke(
                $panel,
                'componentIdOf',
                [$event],
            ),
            "Non-object sender must collapse to '[]'.",
        );
    }

    public function testComponentIdOfReturnsEmptyForUnregisteredSender(): void
    {
        $panel = $this->makePanel(QueuePanel::class);

        $event = new Event();
        $event->sender = new stdClass();

        self::assertSame(
            '',
            $this->invoke(
                $panel,
                'componentIdOf',
                [$event]
            ),
            "Unregistered sender must collapse to '[]'.",
        );
    }

    public function testComponentMatchesQueueBaseHandlesStringConfigArrayAndObjectInputs(): void
    {
        $panel = $this->makePanel(QueuePanel::class);

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

    public function testErrorMessageOfReturnsExceptionMessageOrEmpty(): void
    {
        $panel = $this->makePanel(QueuePanel::class);

        self::assertSame(
            'boom',
            $this->invoke(
                $panel,
                'errorMessageOf',
                [new RuntimeException('boom')],
            ),
            'Throwable must surface its message.',
        );
        self::assertSame(
            '',
            $this->invoke(
                $panel,
                'errorMessageOf',
                ['not-throwable']
            ),
            "Non-throwable input must collapse to ''.",
        );
    }

    public function testGetDetailRendersEmptyStateWhenNoRecords(): void
    {
        $panel = $this->makePanel(QueuePanel::class);

        $panel->data = ['records' => []];

        $html = $panel->getDetail();

        self::assertStringContainsString(
            'No jobs queued in this request',
            $html,
            'Empty queue panel must surface the empty-state hint.',
        );
    }

    public function testGetDetailRendersExecutedAndErrorStatsAndAsyncHint(): void
    {
        $panel = $this->makePanel(QueuePanel::class);

        $panel->data = [
            'records' => [
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
        ];

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
        $panel = $this->makePanel(QueuePanel::class);

        $panel->data = [
            'records' => [
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
        ];

        self::assertNotEmpty(
            $panel->getDetail(),
            'Detail view must produce markup.',
        );
    }

    public function testGetNameAndIconAndIsEnabled(): void
    {
        $panel = $this->makePanel(QueuePanel::class);

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
        $panel = $this->makePanel(QueuePanel::class);

        $records = [];

        for ($i = 0; $i < 3; $i++) {
            $records[] = $this->makeRecord(['eventType' => $i === 2 ? JobRecord::TYPE_ERROR : JobRecord::TYPE_PUSH]);
        }

        $this->setInaccessibleProperty(
            $panel,
            'records',
            $records,
        );

        $items = $this->invoke(
            $panel,
            'getToolbarItems',
        );

        self::assertIsArray(
            $items,
            'Items must be a list.',
        );
        self::assertCount(
            2,
            $items,
            'Errors must surface a second chip.',
        );

        $count = $items[0] ?? self::fail('Expected count chip.');
        $errors = $items[1] ?? self::fail('Expected errors chip.');

        self::assertIsArray(
            $count,
            'Count chip must be an array.',
        );
        self::assertIsArray(
            $errors,
            'Errors chip must be an array.',
        );
        self::assertSame(
            3,
            $count['value'] ?? null,
            'Count chip value must equal total events.',
        );
        self::assertSame(
            'danger',
            $errors['status'] ?? null,
            "Errors chip must use 'danger' status.",
        );
        self::assertSame(
            1,
            $errors['value'] ?? null,
            'Errors chip must count only error events.',
        );
    }

    public function testGetToolbarItemsEmitsCountChipWhenComponentConfiguredAndNoRecords(): void
    {
        $panel = $this->makePanel(QueuePanel::class);

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
            'Items must be a list when a queue component is registered.',
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

    public function testGetToolbarItemsReturnsNullWhenNoComponentAndNoRecords(): void
    {
        $panel = $this->makePanel(QueuePanel::class);

        self::assertNull(
            $this->invoke(
                $panel,
                'getToolbarItems',
            ),
            'No queue component and no records must skip the toolbar.',
        );
    }

    public function testInitCapturesErrorEventAndExtractsMessage(): void
    {
        $panel = $this->makePanel(QueuePanel::class);

        $job = new stdClass();

        Event::trigger(
            'yii\\queue\\Queue',
            'beforeExec',
            $this->makeQueueEvent(job: $job),
        );
        Event::trigger(
            'yii\\queue\\Queue',
            'afterError',
            $this->makeQueueEvent(job: $job, error: new RuntimeException('job failed')),
        );

        $saved = $panel->save();

        $errorRecord = $saved['records'][0] ?? self::fail('Expected the error record.');

        self::assertSame(
            JobRecord::TYPE_ERROR,
            $errorRecord['eventType'] ?? null,
            "Captured event must be 'error'."
        );
        self::assertSame(
            'job failed',
            $errorRecord['error'] ?? null,
            'Error message must round-trip.'
        );

        Event::offAll();
    }

    public function testInitRegistersQueueJobActionAndPushListener(): void
    {
        $panel = $this->makePanel(QueuePanel::class);

        self::assertArrayHasKey(
            'queue-job',
            $panel->actions,
            "Init must register the 'queue-job' action.",
        );

        Event::trigger(
            'yii\\queue\\Queue',
            'afterPush',
            $this->makeQueueEvent(jobId: 'job-7'),
        );

        $saved = $panel->save();

        self::assertCount(
            1,
            $saved['records'],
            'Wildcard listener must capture push events.',
        );

        $record = $saved['records'][0];

        self::assertSame(
            JobRecord::TYPE_PUSH,
            $record['eventType'] ?? null,
            "Captured event type must be 'push'."
        );
        self::assertSame(
            'job-7',
            $record['jobId'] ?? null,
            "'jobId' must round-trip from the event.",
        );

        Event::offAll();
    }

    public function testInitTracksExecDurationViaBeforeAndAfterExecPair(): void
    {
        $panel = $this->makePanel(QueuePanel::class);

        $job = new stdClass();

        Event::trigger(
            'yii\\queue\\Queue',
            'beforeExec',
            $this->makeQueueEvent(job: $job),
        );
        Event::trigger(
            'yii\\queue\\Queue',
            'afterExec',
            $this->makeQueueEvent(job: $job),
        );

        $saved = $panel->save();

        $execRecord = $saved['records'][0] ?? self::fail('Expected one record.');

        self::assertSame(
            JobRecord::TYPE_EXEC,
            $execRecord['eventType'] ?? null,
            "Captured event must be 'exec'."
        );
        self::assertNotNull(
            $execRecord['duration'] ?? null,
            'Exec duration must be computed from begin/end pair.'
        );

        Event::offAll();
    }

    public function testJobOfReturnsTheJobObjectOrNullWhenMissing(): void
    {
        $panel = $this->makePanel(QueuePanel::class);

        $job = new stdClass();

        self::assertSame(
            $job,
            $this->invoke($panel, 'jobOf', [$this->makeQueueEvent(job: $job)]),
            'Job object must round-trip from the event.',
        );
        self::assertNull(
            $this->invoke($panel, 'jobOf', [new Event()]),
            "Missing 'job' property must yield 'null'."
        );
    }

    public function testResolveRecordsFallsBackToSavedPayloadWhenLiveIsEmpty(): void
    {
        $panel = $this->makePanel(QueuePanel::class);

        $panel->data = [
            'records' => [['eventType' => 'saved']],
        ];

        $records = $this->invoke(
            $panel,
            'resolveRecords',
        );

        self::assertIsArray(
            $records,
            'Records must be a list.',
        );

        $first = $records[0] ?? self::fail('Expected one record.');

        self::assertSame(
            ['eventType' => 'saved'],
            $first,
            'Saved payload must be used when live is empty.',
        );
    }

    public function testResolveRecordsPrefersLiveOverSavedPayload(): void
    {
        $panel = $this->makePanel(QueuePanel::class);

        $this->setInaccessibleProperty(
            $panel,
            'records',
            [['eventType' => 'live']],
        );

        $panel->data = [
            'records' => [['eventType' => 'saved']],
        ];

        $records = $this->invoke($panel, 'resolveRecords');

        self::assertIsArray(
            $records,
            'Records must be a list.',
        );

        $first = $records[0] ?? self::fail('Expected one record.');

        self::assertSame(
            ['eventType' => 'live'],
            $first,
            'Live records must shadow saved payload.',
        );
    }

    public function testResolveRecordsReturnsEmptyWhenLiveAndSavedAreEmpty(): void
    {
        $panel = $this->makePanel(QueuePanel::class);

        self::assertSame(
            [],
            $this->invoke($panel, 'resolveRecords'),
            "No live and no saved means '[]'.",
        );
    }

    public function testScalarToStringCoercesScalarsToStringAndDropsOthers(): void
    {
        $panel = $this->makePanel(QueuePanel::class);

        self::assertSame(
            '42',
            $this->invoke($panel, 'scalarToString', [42]),
            'Int must coerce to string.',
        );
        self::assertSame(
            'hello',
            $this->invoke($panel, 'scalarToString', ['hello']),
            'String must pass through.',
        );
        self::assertSame(
            '',
            $this->invoke($panel, 'scalarToString', [new stdClass()]),
            "Object must collapse to ''.",
        );
    }

    public function testValueToNullableIntKeepsIntsAndDropsOthers(): void
    {
        $panel = $this->makePanel(QueuePanel::class);

        self::assertSame(
            42,
            $this->invoke(
                $panel,
                'valueToNullableInt',
                [42],
            ),
            'Int must round-trip.',
        );
        self::assertNull(
            $this->invoke(
                $panel,
                'valueToNullableInt',
                ['42'],
            ),
            "String must yield 'null'.",
        );
        self::assertNull(
            $this->invoke(
                $panel,
                'valueToNullableInt',
                [null],
            ),
            "'null' must yield 'null'."
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists('yii\\queue\\Queue', false)) {
            eval('namespace yii\\queue; abstract class Queue extends \\yii\\base\\Component {}');
        }
    }

    /**
     * @param object|null $job Job object exposed as the event's `job` public property.
     * @param \Throwable|null $error Exception exposed as the event's `error` public property.
     */
    private function makeQueueEvent(
        object|null $job = null,
        string $jobId = '',
        int|null $ttr = null,
        int|null $delay = null,
        int|null $priority = null,
        int|null $attempt = null,
        mixed $error = null,
    ): Event {
        $event = new class extends Event {
            public object|null $job = null;
            public string $id = '';
            public int|null $ttr = null;
            public int|null $delay = null;
            public int|null $priority = null;
            public int|null $attempt = null;
            public mixed $error = null;
        };

        $event->job = $job ?? new stdClass();
        $event->id = $jobId;
        $event->ttr = $ttr;
        $event->delay = $delay;
        $event->priority = $priority;
        $event->attempt = $attempt;
        $event->error = $error;

        return $event;
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
