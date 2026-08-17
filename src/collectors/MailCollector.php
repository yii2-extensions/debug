<?php

declare(strict_types=1);

namespace yii\debug\collectors;

use Closure;
use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Panel\Mail\MailSnapshot;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\{AbstractPart, TextPart};
use Yii;
use yii\base\Event;
use yii\helpers\FileHelper;
use yii\mail\{BaseMailer, MailEvent, MessageInterface};
use yii\symfonymailer\Message;

use function file_put_contents;
use function is_array;
use function is_string;

/**
 * Captures every mail message dispatched during the request for the Mail panel.
 *
 * Subscribes to `BaseMailer::EVENT_AFTER_SEND` at {@see startup()} and detaches at {@see shutdown()}, persists each
 * message to disk under {@see $mailPath} as a `.eml` file, and records the metadata (sender, recipients, subject,
 * headers, charset, time) consumed by the detail view and the toolbar.
 *
 * Usage example:
 *
 * ```php
 * $snapshot = (new \yii\debug\collectors\MailCollector())->capture();
 * ```
 */
class MailCollector extends Collector
{
    /**
     * Filesystem path (Yii alias) where every captured message is persisted as a `.eml` file.
     */
    public string $mailPath = '@runtime/debug/mail';

    /**
     * @var (Closure(MailEvent): void)|null Active mailer listener, kept so {@see stop()} can detach it.
     */
    private Closure|null $listener = null;
    /**
     * @var array<int, array<string, mixed>> Mail messages captured for the current request, in send order.
     */
    private array $messages = [];

    /**
     * Snapshots the captured messages, with their metadata (time, reply, bcc, cc, from, to, subject, headers, etc.).
     *
     * @return MailSnapshot|null Captured mail payload; `null` when the collector never started.
     */
    public function capture(): MailSnapshot|null
    {
        if (!$this->isStarted()) {
            return null;
        }

        return MailSnapshot::capture($this->messages);
    }

    /**
     * Returns the file names of the captured `.eml` files persisted under {@see $mailPath}.
     *
     * Usage example:
     *
     * ```php
     * $files = $collector->getMessagesFileName();
     * ```
     *
     * @return array<int, string> File names in send order.
     */
    public function getMessagesFileName(): array
    {
        $names = [];

        foreach ($this->messages as $message) {
            if (is_string($message['file'] ?? null)) {
                $names[] = $message['file'];
            }
        }

        return $names;
    }

    /**
     * Returns the stable ID pairing this collector with the Mail panel.
     *
     * Usage example:
     *
     * ```php
     * $id = (new \yii\debug\collectors\MailCollector())->id();
     * ```
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'mail';
    }

    /**
     * Registers the mailer listener that persists each dispatched message and records its metadata.
     */
    protected function start(): void
    {
        $this->messages = [];
        $this->listener = function (MailEvent $event): void {
            $message = $event->message;

            if (!$event->sender instanceof BaseMailer) {
                return;
            }

            $messageData = [
                'bcc' => $this->convertParams($message->getBcc()),
                'cc' => $this->convertParams($message->getCc()),
                'charset' => $message->getCharset(),
                'from' => $this->convertParams($message->getFrom()),
                'isSuccessful' => $event->isSuccessful,
                'reply' => $this->convertParams($message->getReplyTo()),
                'subject' => $message->getSubject(),
                'to' => $this->convertParams($message->getTo()),
            ];

            $this->addMoreInformation($message, $messageData);

            // store message as file
            $fileName = $event->sender->generateMessageFileName();

            $mailPath = Yii::getAlias($this->mailPath);
            FileHelper::createDirectory($mailPath);

            file_put_contents("{$mailPath}/{$fileName}", $message->toString());

            $messageData['file'] = $fileName;

            $this->messages[] = $messageData;
        };

        Event::on(BaseMailer::class, BaseMailer::EVENT_AFTER_SEND, $this->listener);
    }

    /**
     * Detaches the mailer listener and clears the captured messages, so a reused worker process starts clean.
     */
    protected function stop(): void
    {
        if ($this->listener !== null) {
            Event::off(BaseMailer::class, BaseMailer::EVENT_AFTER_SEND, $this->listener);

            $this->listener = null;
        }

        $this->messages = [];
    }

    /**
     * Extracts the plain-text body, prepared headers, and capture time from the Symfony-backed message, mutating
     * `$messageData` in place.
     *
     * No-op for messages that are not {@see Message} instances.
     *
     * @param MessageInterface $message Captured mail message.
     * @param array<string, mixed> $messageData Metadata array to enrich.
     */
    private function addMoreInformation(MessageInterface $message, array &$messageData): void
    {
        if (!$message instanceof Message) {
            return;
        }

        /** @var Email $symfonyMessage */
        $symfonyMessage = $message->getSymfonyEmail();
        /** @var AbstractPart $part */
        $part = $symfonyMessage->getBody();

        $body = null;

        if ($part instanceof TextPart && 'plain' === $part->getMediaSubtype()) {
            $messageData['charset'] = $part->asDebugString();
            $body = $part->getBody();
        }

        $messageData['body'] = $body;
        $messageData['headers'] = $part->getPreparedHeaders()->toString();
        $messageData['time'] = $symfonyMessage->getDate();
    }

    /**
     * Flattens an address attribute into a comma-separated string.
     *
     * Address arrays are joined by their keys (the address strings); scalar and {@see \Stringable} values pass
     * through unchanged; anything else collapses to `''`.
     */
    private function convertParams(mixed $attr): string
    {
        if (is_array($attr)) {
            return implode(
                ', ',
                array_map(
                    static fn(int|string $key): string => (string) $key,
                    array_keys($attr),
                ),
            );
        }

        return Coerce::stringOrNull($attr) ?? '';
    }
}
