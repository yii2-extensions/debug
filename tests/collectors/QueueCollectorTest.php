<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use PHPForge\Debug\Panel\Queue\JobRecord;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use stdClass;
use Throwable;
use Yii;
use yii\base\{Component, Event};
use yii\debug\collectors\QueueCollector;
use yii\debug\tests\support\TestCase;

use function class_exists;

/**
 * Unit tests for {@see QueueCollector} covering the queue lifecycle capture, the component-id resolution, the
 * listener detachment on shutdown, and the payload-narrowing helpers.
 */
#[Group('collector')]
#[Group('queue')]
final class QueueCollectorTest extends TestCase
{
    public function testCaptureRecordsErrorEventAndExtractsMessage(): void
    {
        $collector = $this->makeCollector();

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

        $records = $this->captureEntries($collector);

        $errorRecord = $records[0] ?? self::fail('Expected the error record.');

        self::assertSame(
            JobRecord::TYPE_ERROR,
            $errorRecord->eventType,
            "Captured event must be 'error'."
        );
        self::assertSame(
            'job failed',
            $errorRecord->error,
            'Error message must round-trip.'
        );

        Event::offAll();
    }

    public function testCaptureRecordsPushEvents(): void
    {
        $collector = $this->makeCollector();

        Event::trigger(
            'yii\\queue\\Queue',
            'afterPush',
            $this->makeQueueEvent(jobId: 'job-7'),
        );

        $records = $this->captureEntries($collector);

        self::assertCount(
            1,
            $records,
            'Wildcard listener must capture push events.',
        );

        $record = $records[0];

        self::assertSame(
            JobRecord::TYPE_PUSH,
            $record->eventType,
            "Captured event type must be 'push'."
        );
        self::assertSame(
            'job-7',
            $record->jobId,
            "'jobId' must round-trip from the event.",
        );

        Event::offAll();
    }
    public function testCaptureReturnsNullBeforeStartup(): void
    {
        $this->mockWebApplication();

        self::assertNull(
            (new QueueCollector())->capture(),
            'Idle collector must record nothing.',
        );
    }

    public function testCaptureTracksExecDurationViaBeforeAndAfterExecPair(): void
    {
        $collector = $this->makeCollector();

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

        $records = $this->captureEntries($collector);

        $execRecord = $records[0] ?? self::fail('Expected one record.');

        self::assertSame(
            JobRecord::TYPE_EXEC,
            $execRecord->eventType,
            "Captured event must be 'exec'."
        );
        self::assertNotNull(
            $execRecord->duration,
            'Exec duration must be computed from begin/end pair.'
        );

        Event::offAll();
    }

    public function testComponentIdOfReturnsCachedRegisteredId(): void
    {
        $collector = $this->makeCollector();

        $queueComponent = new Component();

        Yii::$app->set('myQueue', $queueComponent);
        Yii::$app->get('myQueue'); // force instantiation so `getComponents(false)` exposes it

        $event = new Event();

        $event->sender = $queueComponent;

        self::assertSame(
            'myQueue',
            $this->invoke(
                $collector,
                'componentIdOf',
                [$event]
            ),
            'Component id must round-trip from the registered name.',
        );
        self::assertSame(
            'myQueue',
            $this->invoke(
                $collector,
                'componentIdOf',
                [$event]
            ),
            'Cached lookup must return the same id on repeat.',
        );
    }

    public function testComponentIdOfReturnsEmptyForNonObjectSender(): void
    {
        $collector = $this->makeCollector();

        $event = new Event();

        self::assertSame(
            '',
            $this->invoke(
                $collector,
                'componentIdOf',
                [$event],
            ),
            "Non-object sender must collapse to '[]'.",
        );
    }

    public function testComponentIdOfReturnsEmptyForUnregisteredSender(): void
    {
        $collector = $this->makeCollector();

        $event = new Event();
        $event->sender = new stdClass();

        self::assertSame(
            '',
            $this->invoke(
                $collector,
                'componentIdOf',
                [$event]
            ),
            "Unregistered sender must collapse to '[]'.",
        );
    }

    public function testErrorMessageOfReturnsExceptionMessageOrEmpty(): void
    {
        $collector = $this->makeCollector();

        self::assertSame(
            'boom',
            $this->invoke(
                $collector,
                'errorMessageOf',
                [new RuntimeException('boom')],
            ),
            'Throwable must surface its message.',
        );
        self::assertSame(
            '',
            $this->invoke(
                $collector,
                'errorMessageOf',
                ['not-throwable']
            ),
            "Non-throwable input must collapse to ''.",
        );
    }

    public function testIdPairsWithTheQueuePanel(): void
    {
        self::assertSame(
            'queue',
            (new QueueCollector())->id(),
            "Stable ID must be 'queue'.",
        );
    }

    public function testJobOfReturnsTheJobObjectOrNullWhenMissing(): void
    {
        $collector = $this->makeCollector();

        $job = new stdClass();

        self::assertSame(
            $job,
            $this->invoke($collector, 'jobOf', [$this->makeQueueEvent(job: $job)]),
            'Job object must round-trip from the event.',
        );
        self::assertNull(
            $this->invoke($collector, 'jobOf', [new Event()]),
            "Missing 'job' property must yield 'null'."
        );
    }

    public function testScalarToStringCoercesScalarsToStringAndDropsOthers(): void
    {
        $collector = $this->makeCollector();

        self::assertSame(
            '42',
            $this->invoke($collector, 'scalarToString', [42]),
            'Int must coerce to string.',
        );
        self::assertSame(
            'hello',
            $this->invoke($collector, 'scalarToString', ['hello']),
            'String must pass through.',
        );
        self::assertSame(
            '',
            $this->invoke($collector, 'scalarToString', [new stdClass()]),
            "Object must collapse to ''.",
        );
    }

    public function testShutdownDetachesTheQueueListenersAndClearsState(): void
    {
        $collector = $this->makeCollector();

        Event::trigger(
            'yii\\queue\\Queue',
            'afterPush',
            $this->makeQueueEvent(jobId: 'job-before'),
        );

        $collector->shutdown();

        Event::trigger(
            'yii\\queue\\Queue',
            'afterPush',
            $this->makeQueueEvent(jobId: 'job-after'),
        );

        $collector->startup();

        self::assertSame(
            [],
            $this->captureEntries($collector),
            'A restarted collector must not retain records captured while stopped.',
        );

        Event::offAll();
    }

    public function testValueToNullableIntKeepsIntsAndDropsOthers(): void
    {
        $collector = $this->makeCollector();

        self::assertSame(
            42,
            $this->invoke(
                $collector,
                'valueToNullableInt',
                [42],
            ),
            'Int must round-trip.',
        );
        self::assertNull(
            $this->invoke(
                $collector,
                'valueToNullableInt',
                ['42'],
            ),
            "String must yield 'null'.",
        );
        self::assertNull(
            $this->invoke(
                $collector,
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
     * Captures the queue records, failing when the started collector produces no snapshot.
     *
     * @param QueueCollector $collector Started collector.
     *
     * @return list<JobRecord> Captured job records.
     */
    private function captureEntries(QueueCollector $collector): array
    {
        $snapshot = $collector->capture();

        self::assertNotNull($snapshot, 'Started collector must capture a snapshot.');

        return $snapshot->entries();
    }

    /**
     * Creates a started collector on top of a mocked web application.
     *
     * @return QueueCollector Started collector.
     */
    private function makeCollector(): QueueCollector
    {
        $this->mockWebApplication();

        $collector = new QueueCollector();

        $collector->startup();

        return $collector;
    }

    /**
     * @param object|null $job Job object exposed as the event's `job` public property.
     * @param Throwable|null $error Exception exposed as the event's `error` public property.
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
}
