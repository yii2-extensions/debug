<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use PHPForge\Debug\Panel\Event\EventRow;
use PHPUnit\Framework\Attributes\Group;
use stdClass;
use yii\base\{Component, Event};
use yii\debug\collectors\EventCollector;
use yii\debug\tests\support\TestCase;

/**
 * Unit tests for {@see EventCollector} covering the wildcard event capture, the listener detachment on shutdown, and
 * the startup/shutdown lifecycle.
 */
#[Group('collector')]
#[Group('event')]
final class EventCollectorTest extends TestCase
{
    public function testCaptureMarksStaticEventsWithSenderClassFromString(): void
    {
        $collector = $this->makeCollector();

        $event = new Event();

        $this->setInaccessibleProperty(
            $event,
            'sender',
            stdClass::class,
        );

        Event::trigger(
            stdClass::class,
            'static.event',
            $event,
        );

        $captured = $this->captureEntries($collector)[0] ?? self::fail('Expected one captured event.');

        self::assertSame(
            '1',
            $captured->isStatic,
            "Static event must mark 'isStatic' as '1'.",
        );
        self::assertSame(
            stdClass::class,
            $captured->senderClass,
            'Class-level sender must round-trip as a string.',
        );
    }

    public function testCaptureRecordsEventsFiredByWildcardListener(): void
    {
        $collector = $this->makeCollector();

        $sender = new Component();

        $sender->trigger('test.event');

        $captured = $this->captureEntries($collector)[0] ?? self::fail('Expected one captured event.');

        self::assertSame(
            'test.event',
            $captured->name,
            'Captured `name` must match the trigger.',
        );
        self::assertSame(
            Component::class,
            $captured->senderClass,
            'Captured `senderClass` must match the sender FQCN.',
        );
        self::assertSame(
            '0',
            $captured->isStatic,
            "Object sender must mark 'isStatic' as '0'.",
        );
    }

    public function testCaptureReturnsEmptyEntriesWhenNoEventsFired(): void
    {
        $collector = $this->makeCollector();

        self::assertSame(
            [],
            $this->captureEntries($collector),
            'No fired events means an empty payload.',
        );
    }

    public function testCaptureReturnsNullBeforeStartup(): void
    {
        $this->mockWebApplication();

        self::assertNull(
            (new EventCollector())->capture(),
            'Idle collector must record nothing.',
        );
    }

    public function testFailedContextDoesNotBreakEventPropagation(): void
    {
        $collector = $this->makeCollector();

        $collector->captureContext = true;

        $event = new \yii\base\ViewEvent();

        (new Component())->trigger('beforeRender', $event);

        self::assertSame(
            'failed',
            ($this->captureEntries($collector)[0]
                ?? self::fail('Expected one captured row.'))->inspection()?->getContextStatus(),
            'Invalid view metadata must be reported as a diagnostic failure.',
        );
    }

    public function testIdPairsWithTheEventsPanel(): void
    {
        self::assertSame(
            'event',
            (new EventCollector())->id(),
            "Stable ID must be 'event'.",
        );
    }

    public function testOptInContextCapturesViewMetadataWithoutPayloadValues(): void
    {
        $collector = $this->makeCollector();

        $collector->captureContext = true;
        $collector->traceLimit = 8;

        $event = new \yii\base\ViewEvent(
            [
                'viewFile' => '/views/site/index.php',
                'params' => ['password' => 'SENSITIVE_EVENT_VALUE'],
                'output' => 'SENSITIVE_RENDERED_OUTPUT',
            ]
        );

        (new Component())->trigger('beforeRender', $event);

        $row = ($this->captureEntries($collector)[0] ?? self::fail('Expected one captured row.'));

        $inspection = $row->inspection();

        self::assertNotNull(
            $inspection,
            'Opt-in capture must enrich the event.',
        );
        self::assertSame(
            '/views/site/index.php',
            $inspection->getContext()['View file'] ?? null,
            'The selected view must be inspectable.',
        );
        self::assertSame(
            'captured',
            $inspection->getContextStatus(),
            'Context availability must be explicit.',
        );
        self::assertNotEmpty(
            $inspection->getTrace(),
            'Opt-in source traces must record file locations.',
        );
        self::assertLessThanOrEqual(
            8,
            count($inspection->getTrace()),
            'Source traces must respect their configured depth.',
        );

        $serialized = json_encode($row->jsonSerialize(), JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString(
            'SENSITIVE_EVENT_VALUE',
            $serialized,
            'Parameter values must never be captured.',
        );
        self::assertStringNotContainsString(
            'SENSITIVE_RENDERED_OUTPUT',
            $serialized,
            'Rendered output must never be captured.',
        );
    }

    public function testOptInContextIdentifiesTheActionAtObservationTime(): void
    {
        $collector = $this->makeCollector();

        $collector->captureContext = true;

        $controller = new \yii\base\Controller('site', \Yii::$app);
        $action = new \yii\base\Action('index', $controller);
        $event = new \yii\base\ActionEvent($action);

        $controller->trigger('beforeAction', $event);

        $context = ($this->captureEntries($collector)[0] ?? self::fail('Expected one captured row.'))
            ->inspection()
            ?->getContext();

        self::assertSame(
            [
                'Action ID' => 'index',
                'Controller ID' => 'site',
                'Continue allowed (observed)' => 'Yes',
            ],
            $context,
            'Action context must identify the actual action without dumping its result.',
        );
    }

    public function testShutdownDetachesTheWildcardListenerAndClearsState(): void
    {
        $collector = $this->makeCollector();

        (new Component())->trigger('before.shutdown');

        $collector->shutdown();

        (new Component())->trigger('after.shutdown');

        self::assertSame(
            [],
            $this->getInaccessibleProperty($collector, 'events'),
            'A stopped collector must not retain events fired after listener detachment.',
        );
    }

    public function testStoppedInstanceEventsDoNotReachTheGlobalObserver(): void
    {
        $collector = $this->makeCollector();

        $component = new Component();

        $executed = false;

        $component->on(
            'stopped',
            static function (Event $event) use (&$executed): void {
                $executed = true;
                $event->handled = true;
            }
        );

        $component->trigger('stopped');

        self::assertTrue(
            $executed,
            'The instance handler must execute normally.',
        );
        self::assertSame(
            [],
            $this->captureEntries($collector),
            'Global observation must not claim to capture stopped instance events.',
        );
    }

    protected function tearDown(): void
    {
        Event::offAll();

        parent::tearDown();
    }

    /**
     * Captures the event rows, failing when the started collector produces no snapshot.
     *
     * @param EventCollector $collector Started collector.
     *
     * @return list<EventRow> Captured event rows.
     */
    private function captureEntries(EventCollector $collector): array
    {
        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'Started collector must capture a snapshot.',
        );

        return $snapshot->entries();
    }

    /**
     * Creates a started collector on top of a mocked web application.
     *
     * @return EventCollector Started collector.
     */
    private function makeCollector(): EventCollector
    {
        $this->mockWebApplication();

        $collector = new EventCollector();

        $collector->startup();

        return $collector;
    }
}
