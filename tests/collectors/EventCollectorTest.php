<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use PHPForge\Debug\Panel\Event\EventRow;
use PHPUnit\Framework\Attributes\{Group, TestWith};
use stdClass;
use Xepozz\InternalMocker\MockerState;
use Yii;
use yii\base\{Action, ActionEvent, Component, Controller, Event, ViewEvent};
use yii\debug\collectors\EventCollector;
use yii\debug\tests\support\{Captured, TestCase};

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
            Captured::event(new EventCollector()),
            'Idle collector must record nothing.',
        );
    }

    public function testFailedContextDoesNotBreakEventPropagation(): void
    {
        $collector = $this->makeCollector();

        $collector->captureContext = true;

        $event = new ViewEvent();

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

        $event = new ViewEvent(
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
        self::assertNotSame(
            [],
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

        $controller = new Controller('site', Yii::$app);
        $action = new Action('index', $controller);
        $event = new ActionEvent($action);

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

    #[TestWith([false, 0])]
    #[TestWith([true, 0])]
    #[TestWith([false, 3])]
    public function testOptionalDiagnosticsReportDisabledAndUnsupportedStates(bool $context, int $depth): void
    {
        $collector = $this->makeCollector();

        $collector->captureContext = $context;
        $collector->traceLimit = $depth;

        (new Component())->trigger('synthetic.plain-event');

        $row = $this->captureEntries($collector)[0] ?? self::fail('Expected the emitted event.');
        $inspection = $row->inspection();

        if (!$context && $depth === 0) {
            self::assertNull(
                $inspection,
                'Default capture must not allocate opt-in diagnostics.',
            );

            return;
        }

        self::assertNotNull(
            $inspection,
            'Requested diagnostics must carry explicit status.',
        );
        self::assertSame(
            $context ? 'unsupported' : 'disabled',
            $inspection->getContextStatus(),
            'Plain events must distinguish unsupported context from disabled capture.',
        );
        self::assertSame(
            $depth > 0 ? 'captured' : 'disabled',
            $inspection->getTraceStatus(),
            'Trace status must respect the configured opt-in depth.',
        );

        foreach ($inspection->getTrace() as $frame) {
            self::assertStringNotContainsString(
                '/src/collectors/EventCollector.php',
                $frame,
                'Source traces must omit the collector implementation.',
            );
        }
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

    #[TestWith([1])]
    #[TestWith([16])]
    public function testTraceFiltersInstrumentationWithinItsBoundedSourceWindow(int $depth): void
    {
        $collector = $this->makeCollector();

        $collector->traceLimit = $depth;

        $frames = array_fill(0, 31, ['file' => Yii::getAlias('@yii/base/Event.php'), 'line' => 1]);
        $frames[] = ['file' => '/app/Boundary.php', 'line' => 32];
        $frames[] = ['file' => '/app/OutsideWindow.php', 'line' => 33];

        // Model the native limit, including zero's unbounded behavior, without fixing the mock to one call signature.
        foreach (range(0, count($frames) + 1) as $limit) {
            MockerState::addCondition(
                'yii\\debug\\collectors',
                'debug_backtrace',
                [DEBUG_BACKTRACE_IGNORE_ARGS, $limit],
                $limit === 0 ? $frames : array_slice($frames, 0, $limit),
            );
        }

        try {
            (new Component())->trigger('bounded.trace');

            $inspection = ($this->captureEntries($collector)[0] ?? self::fail('Expected one captured event.'))
                ->inspection();

            self::assertNotNull(
                $inspection,
                'An enabled trace must preserve its diagnostic state.',
            );
            self::assertSame(
                'captured',
                $inspection->getTraceStatus(),
                'A valid bounded trace must be captured.',
            );
            self::assertSame(
                ['/app/Boundary.php:32'],
                $inspection->getTrace(),
                'Instrumentation must be omitted while retaining the last application frame inside the source window.',
            );
        } finally {
            MockerState::resetState();
        }
    }

    #[TestWith([0, 'disabled'])]
    #[TestWith([8, 'failed'])]
    public function testTraceStatusPreservesContextAndPropagationWhenFrameworkPathIsUnavailable(int $depth, string $status): void
    {
        $collector = $this->makeCollector();

        $collector->captureContext = true;
        $collector->traceLimit = $depth;

        $component = new Component();
        $event = new ViewEvent(['viewFile' => '/views/site/index.php']);
        $executed = false;

        Event::on(
            Component::class,
            'beforeRender',
            static function () use (&$executed): void {
                $executed = true;
            },
        );

        $aliases = Yii::$aliases;

        try {
            Yii::setAlias('@yii', null);

            $component->trigger('beforeRender', $event);
        } finally {
            Yii::$aliases = $aliases;
        }

        $entries = $this->captureEntries($collector);

        self::assertCount(
            1,
            $entries,
            'A trace failure must not discard the observed event.',
        );

        $inspection = $entries[0]->inspection();

        self::assertNotNull(
            $inspection,
            'A trace failure must retain the event inspection.',
        );
        self::assertSame(
            $status,
            $inspection->getTraceStatus(),
            'Unavailable trace prerequisites must not turn a disabled feature into a failed capture.',
        );
        self::assertSame(
            [],
            $inspection->getTrace(),
            'Failed trace capture must not expose partial trace data.',
        );
        self::assertSame(
            'captured',
            $inspection->getContextStatus(),
            'A trace failure must not change the successful context status.',
        );
        self::assertSame(
            [
                'View file' => '/views/site/index.php',
                'Continue allowed (observed)' => 'Yes',
            ],
            $inspection->getContext(),
            'A trace failure must preserve the independently captured context.',
        );
        self::assertTrue(
            $executed,
            'A trace failure must not prevent later event handlers from running.',
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
        $snapshot = Captured::event($collector);

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
