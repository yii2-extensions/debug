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

    public function testIdPairsWithTheEventsPanel(): void
    {
        self::assertSame(
            'event',
            (new EventCollector())->id(),
            "Stable ID must be 'event'.",
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
