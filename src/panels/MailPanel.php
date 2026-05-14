<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Stringable;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\{AbstractPart, TextPart};
use Throwable;
use Yii;
use yii\base\Event;
use yii\debug\{LogTarget, Panel};
use yii\debug\models\search\MailSearch;
use yii\helpers\FileHelper;
use yii\helpers\Url;
use yii\mail\{BaseMailer, MailEvent,MessageInterface};
use yii\symfonymailer\Message;

use function count;
use function is_array;
use function is_scalar;
use function is_string;

/**
 * Captures every mail message dispatched during the request and renders them in the Mail panel.
 *
 * Subscribes to `BaseMailer::EVENT_AFTER_SEND` at {@see init()} time, persists each message to disk under
 * {@see $mailPath} as a `.eml` file, and records the metadata (sender, recipients, subject, headers, charset, time)
 * consumed by the detail view and the toolbar.
 */
class MailPanel extends Panel
{
    /**
     * Filesystem path (Yii alias) where every captured message is persisted as a `.eml` file.
     */
    public string $mailPath = '@runtime/debug/mail';

    /**
     * @var array<int, array<string, mixed>> Mail messages captured for the current request, in send order.
     */
    private array $messages = [];

    /**
     * Renders the detail view with the mail card list.
     */
    public function getDetail(): string
    {
        $searchModel = new MailSearch();

        $dataProvider = $searchModel->search(Yii::$app->request->get(), self::normalizeMessages($this->data));

        return Yii::$app->view->render(
            'panels/mail/detail',
            [
                'dataProvider' => $dataProvider,
                'panel' => $this,
                'searchModel' => $searchModel,
            ],
        );
    }

    /**
     * Returns the file names of the captured `.eml` files persisted under {@see $mailPath}.
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
     * Returns the panel display name.
     */
    public function getName(): string
    {
        return 'Mail';
    }

    /**
     * Renders the toolbar summary chip with the captured message count.
     */
    public function getSummary(): string
    {
        return Yii::$app->view->render(
            'panels/mail/summary',
            [
                'panel' => $this,
                'mailCount' => count(self::normalizeMessages($this->data)),
            ],
        );
    }

    /**
     * Returns the toolbar icon name.
     */
    public function getToolbarIcon(): string
    {
        return 'mail';
    }

    /**
     * Registers the mailer listener that persists each dispatched message and records its metadata.
     */
    public function init(): void
    {
        parent::init();

        Event::on(
            BaseMailer::class,
            BaseMailer::EVENT_AFTER_SEND,
            function (MailEvent $event): void {
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
            },
        );
    }

    /**
     * Snapshots the captured messages, with their metadata (time, reply, bcc, cc, from, to, subject, headers, etc.).
     *
     * @return array<int, array<string, mixed>> Mail records in send order.
     */
    public function save(): array
    {
        return $this->messages;
    }

    /**
     * Builds the toolbar items.
     *
     * Returns the captured count when the current request sent at least one message; otherwise looks at the previous
     * captured request and surfaces a `cross-request` chip pointing at its panel when it carries mail (handles the
     * Post-Redirect-Get flow where the mail was sent by the request before the redirect).
     *
     * @return array<int, array<string, mixed>>|null Toolbar items, or `null` when neither the current nor the previous
     * request captured any mail.
     */
    protected function getToolbarItems(): array|null
    {
        if (!is_array($this->data)) {
            return [
                [
                    'status' => 'warning',
                    'value' => '!',
                ],
            ];
        }

        $mailCount = count(self::normalizeMessages($this->data));

        if ($mailCount > 0) {
            return [['value' => $mailCount]];
        }

        $previous = $this->findPreviousRequestWithMail();

        if ($previous === null) {
            return null;
        }

        return [
            [
                'value' => $previous['count'],
                'status' => 'cross-request',
                'title' => sprintf(
                    'Sent in the previous request (%s %s) — open it.',
                    $previous['method'],
                    $previous['shortUrl'],
                ),
                'url' => $previous['url'],
            ],
        ];
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
     * Address arrays are joined by their keys (the address strings); scalar and {@see Stringable} values pass through
     * unchanged; anything else collapses to `''`.
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

        if (is_scalar($attr) || $attr instanceof Stringable) {
            return (string) $attr;
        }

        return '';
    }

    /**
     * Looks at the debug manifest for the request immediately preceding the current one and returns its mail count
     * when non-zero, falling back to the most-recent manifest entry when the current tag is not yet listed (race
     * during the very first response of a session).
     *
     * @return array{count: int, method: string, shortUrl: string, url: string}|null Cross-request chip payload, or
     * `null` when no usable previous request exists.
     */
    private function findPreviousRequestWithMail(): array|null
    {
        $module = $this->module;

        if ($module === null) {
            return null;
        }

        $logTarget = $module->logTarget;

        if (!$logTarget instanceof LogTarget) {
            return null;
        }

        try {
            $manifest = $logTarget->loadManifest();
        } catch (Throwable) {
            return null;
        }

        if ($manifest === []) {
            return null;
        }

        $currentTag = $this->tag;

        $previousTag = null;
        $found = false;

        foreach ($manifest as $tag => $_summary) {
            if ($found) {
                $previousTag = $tag;
                break;
            }

            if ($tag === $currentTag) {
                $found = true;
            }
        }

        if ($previousTag === null) {
            $previousTag = array_key_first($manifest);

            if ($previousTag === $currentTag) {
                return null;
            }
        }

        $summary = $manifest[$previousTag] ?? [];
        $dataFile = "{$module->dataPath}/{$previousTag}.data";

        if (!is_file($dataFile)) {
            return null;
        }

        $blob = @unserialize((string) @file_get_contents($dataFile));

        if (!is_array($blob)) {
            return null;
        }

        $mailRaw = $blob['mail'] ?? null;

        if (is_string($mailRaw)) {
            $mailRaw = @unserialize($mailRaw);
        }

        $count = 0;

        if (is_array($mailRaw)) {
            foreach ($mailRaw as $entry) {
                if (is_array($entry)) {
                    $count++;
                }
            }
        }

        if ($count === 0) {
            return null;
        }

        $method = is_string($summary['method'] ?? null) ? $summary['method'] : '';
        $url = is_string($summary['url'] ?? null) ? $summary['url'] : '';
        $shortUrlPath = $url === '' ? null : parse_url($url, PHP_URL_PATH);
        $shortUrl = is_string($shortUrlPath) && $shortUrlPath !== '' ? $shortUrlPath : $url;

        $panelUrl = Url::toRoute(
            [
                '/' . $module->getUniqueId() . '/default/view',
                'panel' => $this->id,
                'tag' => $previousTag,
            ],
        );

        return [
            'count' => $count,
            'method' => $method,
            'shortUrl' => $shortUrl,
            'url' => $panelUrl,
        ];
    }

    /**
     * Narrows the saved mail rows into a string-keyed list, dropping non-array entries and non-string keys inside
     * each entry.
     *
     * @return array<int, array<string, mixed>> Sanitized mail records in original order.
     */
    private static function normalizeMessages(mixed $messages): array
    {
        if (!is_array($messages)) {
            return [];
        }

        $normalized = [];

        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }

            $row = [];

            foreach ($message as $key => $value) {
                if (is_string($key)) {
                    $row[$key] = $value;
                }
            }

            $normalized[] = $row;
        }

        return $normalized;
    }
}
