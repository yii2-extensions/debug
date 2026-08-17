<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use PHPForge\Debug\Panel\Mail\MailMessage;
use PHPUnit\Framework\Attributes\Group;
use Stringable;
use yii\base\Event;
use yii\debug\collectors\MailCollector;
use yii\debug\tests\support\TestCase;
use yii\mail\{BaseMailer, MailEvent, MessageInterface};
use yii\symfonymailer\Mailer;

use function sys_get_temp_dir;

/**
 * Unit tests for {@see MailCollector} covering the mailer listener capture, the recipient-list flattening, the
 * `.eml` file bookkeeping, and the startup/shutdown lifecycle.
 */
#[Group('collector')]
#[Group('mail')]
final class MailCollectorTest extends TestCase
{
    public function testAddMoreInformationIsNoOpForNonSymfonyMessages(): void
    {
        $collector = $this->makeCollector();

        $messageData = ['existing' => 'kept'];

        $args = [self::createStub(MessageInterface::class)];

        $args[1] = &$messageData;

        $this->invoke(
            $collector,
            'addMoreInformation',
            $args,
        );

        self::assertArrayNotHasKey(
            'body',
            $messageData,
            "Non-Symfony path must not add a 'body' slot.",
        );
        self::assertArrayNotHasKey(
            'headers',
            $messageData,
            "Non-Symfony path must not add a 'headers' slot.",
        );
        self::assertArrayNotHasKey(
            'time',
            $messageData,
            "Non-Symfony path must not add a 'time' slot.",
        );
    }

    public function testCaptureReturnsEmptyArrayWhenNoMessagesCaptured(): void
    {
        $collector = $this->makeCollector();

        self::assertSame(
            [],
            $this->captureEntries($collector),
            'Fresh panel must produce an empty payload.',
        );
    }
    public function testCaptureReturnsNullBeforeStartup(): void
    {
        $this->mockWebApplication();

        self::assertNull(
            (new MailCollector())->capture(),
            'Idle collector must record nothing.',
        );
    }

    public function testConvertParamsHandlesArrayScalarAndStringableInputs(): void
    {
        $collector = $this->makeCollector();

        self::assertSame(
            'a@x.com, b@x.com',
            $this->invoke(
                $collector,
                'convertParams',
                [
                    [
                        'a@x.com' => 'Alice',
                        'b@x.com' => 'Bob',
                    ],
                ],
            ),
            'Address array must flatten to a comma-separated key list.',
        );
        self::assertSame(
            'plain@x.com',
            $this->invoke(
                $collector,
                'convertParams',
                ['plain@x.com'],
            ),
            'Scalar input must pass through unchanged.',
        );
        self::assertSame(
            'stringable@x.com',
            $this->invoke(
                $collector,
                'convertParams',
                [
                    new class implements Stringable {
                        public function __toString(): string
                        {
                            return 'stringable@x.com';
                        }
                    },
                ],
            ),
            'Stringable input must be coerced to string.',
        );
        self::assertSame(
            '',
            $this->invoke(
                $collector,
                'convertParams',
                [null],
            ),
            "Unsupported input must collapse to ''.",
        );
    }

    public function testGetMessagesFileNameDropsNonStringEntries(): void
    {
        $collector = $this->makeCollector();

        $this->setInaccessibleProperty(
            $collector,
            'messages',
            [
                ['file' => 'first.eml'],
                ['file' => 42],
                ['no-file-key' => 'ignored'],
                ['file' => 'second.eml'],
            ],
        );

        self::assertSame(
            ['first.eml', 'second.eml'],
            $collector->getMessagesFileName(),
            "Only string 'file' values must round-trip.",
        );
    }

    public function testIdPairsWithTheMailPanel(): void
    {
        self::assertSame(
            'mail',
            (new MailCollector())->id(),
            "Stable ID must be 'mail'.",
        );
    }

    public function testInitCapturesMessagesViaMailerAfterSendListener(): void
    {
        $collector = $this->makeCollector();

        $mailer = new Mailer(
            [
                'useFileTransport' => true,
                'fileTransportPath' => sys_get_temp_dir() . '/debug-mail',
            ],
        );

        $message = $mailer->compose()
            ->setFrom('from@example.com')
            ->setTo('to@example.com')
            ->setSubject('Hello')
            ->setTextBody('Body text');

        $event = new MailEvent(
            [
                'message' => $message,
                'isSuccessful' => true,
            ],
        );

        $event->sender = $mailer;

        Event::trigger(
            BaseMailer::class,
            BaseMailer::EVENT_AFTER_SEND,
            $event,
        );

        $saved = $this->captureEntries($collector);

        $captured = $saved[0] ?? self::fail('Expected one captured message.');

        self::assertSame(
            'from@example.com',
            $captured->from,
            'FROM must round-trip.',
        );
        self::assertSame(
            ['to@example.com'],
            $captured->to,
            'TO must round-trip.',
        );
        self::assertSame(
            'Hello',
            $captured->subject,
            'SUBJECT must round-trip.',
        );
        self::assertTrue(
            $captured->isSuccessful,
            'IS_SUCCESSFUL must round-trip.',
        );
        self::assertNotSame('', $captured->file, 'FILE must be assigned.');

        Event::offAll();
    }

    public function testInitIgnoresEventsTriggeredByNonMailerSenders(): void
    {
        $collector = $this->makeCollector();

        $event = new MailEvent(
            [
                'message' => self::createStub(MessageInterface::class),
                'isSuccessful' => true,
            ],
        );

        Event::trigger(
            BaseMailer::class,
            BaseMailer::EVENT_AFTER_SEND,
            $event,
        );

        self::assertSame(
            [],
            $this->captureEntries($collector),
            'Non-mailer sender must short-circuit before capture.',
        );

        Event::offAll();
    }

    public function testShutdownDetachesTheMailerListenerAndClearsMessages(): void
    {
        $collector = $this->makeCollector();

        $collector->shutdown();

        $collector->startup();

        self::assertSame(
            [],
            $collector->getMessagesFileName(),
            'A restarted collector must not retain messages captured before shutdown.',
        );
    }

    protected function tearDown(): void
    {
        Event::offAll();

        parent::tearDown();
    }


    /**
     * Captures the mail rows, failing when the started collector produces no snapshot.
     *
     * @param MailCollector $collector Started collector.
     *
     * @return list<MailMessage> Captured mail messages.
     */
    private function captureEntries(MailCollector $collector): array
    {
        $snapshot = $collector->capture();

        self::assertNotNull($snapshot, 'Started collector must capture a snapshot.');

        return $snapshot->entries();
    }

    /**
     * Creates a started collector on top of a mocked web application.
     *
     * @return MailCollector Started collector.
     */
    private function makeCollector(): MailCollector
    {
        $this->mockWebApplication();

        $collector = new MailCollector();

        $collector->startup();

        return $collector;
    }
}
