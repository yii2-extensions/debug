<?php

declare(strict_types=1);

namespace yii\debug\tests\collectors;

use PHPForge\Debug\Panel\Mail\MailMessage;
use PHPUnit\Framework\Attributes\Group;
use Stringable;
use Xepozz\InternalMocker\MockerState;
use Yii;
use yii\base\Event;
use yii\debug\collectors\MailCollector;
use yii\debug\tests\support\TestCase;
use yii\mail\{BaseMailer, MailEvent, MessageInterface};
use yii\symfonymailer\Mailer;

use function fileperms;
use function glob;
use function sys_get_temp_dir;
use function touch;
use function uniqid;

use const DIRECTORY_SEPARATOR;

/**
 * Unit tests for {@see MailCollector} covering the mailer listener capture, the recipient-list flattening, the `.eml`
 * file bookkeeping, and the startup/shutdown lifecycle.
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
                ['file' => ''],
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

        $path = sys_get_temp_dir() . '/yii2-debug-mail-capture-' . uniqid('', true);

        $collector->mailPath = $path;

        $captured = $this->captureSentMessage($collector);

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
        self::assertNotSame(
            '',
            $captured->file,
            'FILE must be assigned.',
        );
        self::assertSame(
            ['cc@example.com'],
            $captured->cc,
            'CC must round-trip.',
        );
        self::assertSame(
            ['bcc@example.com'],
            $captured->bcc,
            'BCC must round-trip.',
        );
        self::assertSame(
            ['reply@example.com'],
            $captured->replyTo,
            'REPLY-TO must round-trip.',
        );
        self::assertSame(
            'Body text',
            $captured->body,
            'BODY must round-trip.',
        );
        self::assertNotSame(
            '',
            $captured->headers,
            'HEADERS must be assigned.',
        );

        if (PHP_OS_FAMILY !== 'Windows') {
            $permissions = fileperms("{$path}/{$captured->file}");

            self::assertIsInt(
                $permissions,
                'Captured mail permissions must be readable.',
            );
            self::assertSame(
                0o600,
                $permissions & 0o777,
                'Standalone captured mail must default to owner-only mode.',
            );

            $directoryPermissions = fileperms($path);

            self::assertIsInt(
                $directoryPermissions,
                'Captured mail directory permissions must be readable.',
            );
            self::assertSame(
                0o700,
                $directoryPermissions & 0o777,
                'Standalone captured mail directory must default to owner-only mode.',
            );
        }

        unlink("{$path}/{$captured->file}");
        rmdir($path);

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

    public function testInitKeepsMailCaptureFailOpenWhenFileModeCannotBeApplied(): void
    {
        $collector = $this->makeCollector();

        $path = sys_get_temp_dir() . '/yii2-debug-mail-mode-failure-' . uniqid('', true);

        $collector->mailPath = $path;

        MockerState::addCondition(
            'yii\\debug\\collectors',
            'chmod',
            [],
            false,
            true,
        );

        $captured = $this->captureSentMessage($collector);

        self::assertSame(
            '',
            $captured->file,
            'A file-mode failure must omit the unavailable `.eml` reference.',
        );
        self::assertSame(
            [],
            $collector->getMessagesFileName(),
            'Failed mail persistence must not reach the manifest.',
        );

        $files = glob("{$path}/*.eml");

        self::assertSame(
            [],
            $files === false ? [] : $files,
            'A mode failure must remove the partially persisted file.',
        );

        rmdir($path);

        Event::offAll();
    }

    public function testInitKeepsMailCaptureFailOpenWhenFileWriteFails(): void
    {
        $collector = $this->makeCollector();

        $path = sys_get_temp_dir() . '/yii2-debug-mail-write-failure-' . uniqid('', true);

        $collector->mailPath = $path;

        Yii::getLogger()->messages = [];

        MockerState::addCondition(
            'yii\\debug\\collectors',
            'file_put_contents',
            [],
            false,
            true,
        );

        $captured = $this->captureSentMessage($collector);

        self::assertSame(
            '',
            $captured->file,
            'A write failure must omit the unavailable `.eml` reference.',
        );
        self::assertSame(
            [],
            $collector->getMessagesFileName(),
            'Failed mail persistence must not reach the manifest.',
        );

        $warning = Yii::getLogger()->messages[0] ?? self::fail('Persistence failure must be logged.');

        self::assertSame(
            MailCollector::class . '::start',
            $warning[2],
            'Persistence failure must be logged from the collector.',
        );
        self::assertIsString($warning[0], 'Logged warning must be a message string.');
        self::assertStringContainsString(
            'Unable to persist captured mail file:',
            $warning[0],
            'Warning must name the write failure, not a later side effect.',
        );

        rmdir($path);

        Event::offAll();
    }

    public function testInitRejectsUnsafeGeneratedMailFileName(): void
    {
        $collector = $this->makeCollector();

        $path = sys_get_temp_dir() . '/yii2-debug-mail-unsafe-name-' . uniqid('', true);

        $collector->mailPath = $path;

        $mailer = new class ([ 'useFileTransport' => true, 'fileTransportPath' => sys_get_temp_dir() . '/debug-mail', ], ) extends Mailer {
            public function generateMessageFileName(): string
            {
                return '../outside.eml';
            }
        };

        $event = new MailEvent(
            [
                'message' => $mailer->compose()->setTextBody('Body text'),
                'isSuccessful' => true,
            ],
        );

        $event->sender = $mailer;

        Event::trigger(BaseMailer::class, BaseMailer::EVENT_AFTER_SEND, $event);

        $captured = $this->captureEntries($collector)[0] ?? self::fail('Expected one captured message.');

        self::assertSame(
            '',
            $captured->file,
            'Unsafe mailer-generated paths must not reach persistence.',
        );
        self::assertDirectoryDoesNotExist(
            $path,
            'An invalid file name must be rejected before creating storage.',
        );

        Event::offAll();
    }

    public function testReconcileFilesRemovesOnlyAgedUnreferencedMail(): void
    {
        $this->mockWebApplication();

        $path = sys_get_temp_dir() . '/yii2-debug-mail-reconcile-' . uniqid('', true);

        mkdir($path, recursive: true);

        $referenced = "{$path}/referenced.eml";
        $orphan = "{$path}/orphan.eml";
        $fresh = "{$path}/fresh.eml";
        $boundary = "{$path}/boundary.eml";

        file_put_contents($referenced, 'referenced');
        file_put_contents($orphan, 'orphan');
        file_put_contents($fresh, 'fresh');
        file_put_contents($boundary, 'boundary');
        touch($referenced, 10_000);
        touch($orphan, 10_000);
        touch($boundary, 13_600);
        touch($fresh, 100_000);

        MockerState::addCondition(
            'yii\\debug\\collectors',
            'time',
            [],
            100_000,
            true,
        );

        $collector = new MailCollector();

        $collector->mailPath = $path;

        $collector->reconcileFiles(['referenced.eml', '../unsafe.eml']);

        self::assertFileExists(
            $referenced,
            'Manifest-referenced mail must survive reconciliation.',
        );
        self::assertFileDoesNotExist(
            $orphan,
            'Aged unreferenced mail must be removed for eventual cleanup retry.',
        );
        self::assertFileDoesNotExist(
            $boundary,
            'A mail exactly at the grace cutoff must be removed.',
        );
        self::assertFileExists(
            $fresh,
            'Fresh mail must remain available to a concurrent snapshot commit.',
        );

        $collector->removeFiles(['../outside.eml', 'fresh.eml']);

        self::assertFileDoesNotExist(
            $fresh,
            'Explicit cleanup must remove safe captured files only.',
        );

        unlink($referenced);
        rmdir($path);
    }

    public function testReconcileFilesReturnsWhenTheMailGlobFails(): void
    {
        $this->mockWebApplication();

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yii2-debug-mail-glob-failure-' . uniqid('', true);

        $orphan = $path . DIRECTORY_SEPARATOR . 'orphan.eml';

        mkdir($path, recursive: true);
        file_put_contents($orphan, 'orphan');
        touch($orphan, 10_000);

        MockerState::addCondition(
            'yii\\debug\\collectors',
            'glob',
            [$path . DIRECTORY_SEPARATOR . '*.eml'],
            false,
        );

        $collector = new MailCollector();
        $collector->mailPath = $path;

        $collector->reconcileFiles([]);

        self::assertFileExists(
            $orphan,
            'A glob failure must return before attempting orphan cleanup.',
        );

        unlink($orphan);
        rmdir($path);
    }

    public function testSafeMailFileValidationRejectsEmptyAndNestedPaths(): void
    {
        self::assertTrue(
            $this->invokeStatic(MailCollector::class, 'isSafeFile', ['message.eml']),
            'A simple file name must be considered safe.',
        );
        self::assertFalse(
            $this->invokeStatic(MailCollector::class, 'isSafeFile', ['']),
            'An empty file name must be considered unsafe.',
        );
        self::assertFalse(
            $this->invokeStatic(MailCollector::class, 'isSafeFile', ['nested/message.eml']),
            'A nested file path must be considered unsafe.',
        );
        self::assertFalse(
            $this->invokeStatic(MailCollector::class, 'isSafeFile', ['nested\\message.eml']),
            'A nested file path with backslashes must be considered unsafe.',
        );
    }

    public function testShutdownDetachesTheMailerListenerAndClearsMessages(): void
    {
        $collector = $this->makeCollector();

        $collector->shutdown();

        $this->triggerSentMessage();

        self::assertSame(
            [],
            $collector->getMessagesFileName(),
            'A stopped collector must not capture messages after listener detachment.',
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

        self::assertNotNull(
            $snapshot,
            'Started collector must capture a snapshot.',
        );

        return $snapshot->entries();
    }

    private function captureSentMessage(MailCollector $collector): MailMessage
    {
        $this->triggerSentMessage();

        $saved = $this->captureEntries($collector);

        return $saved[0] ?? self::fail('Expected one captured message.');
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

    private function triggerSentMessage(): void
    {
        $mailer = new Mailer(
            [
                'useFileTransport' => true,
                'fileTransportPath' => sys_get_temp_dir() . '/debug-mail',
            ],
        );

        $message = $mailer->compose()
            ->setFrom('from@example.com')
            ->setTo('to@example.com')
            ->setCc('cc@example.com')
            ->setBcc('bcc@example.com')
            ->setReplyTo('reply@example.com')
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
    }
}
