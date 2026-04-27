<?php

declare(strict_types=1);

namespace yii\debug\panels;

use Stringable;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\{AbstractPart, TextPart};
use Yii;
use yii\base\Event;
use yii\debug\LogTarget;
use yii\debug\models\search\Mail;
use yii\debug\Panel;
use yii\helpers\FileHelper;
use yii\mail\{BaseMailer, MailEvent,MessageInterface};
use yii\symfonymailer\Message;

use function count;
use function is_array;
use function is_string;

/**
 * Debugger panel that collects and displays the generated emails.
 */
class MailPanel extends Panel
{
    /**
     * Path where all emails will be saved. should be an alias.
     */
    public string $mailPath = '@runtime/debug/mail';

    /**
     * @var array<int, array<string, mixed>> Current request sent messages
     */
    private array $messages = [];

    public function getDetail(): string
    {
        $searchModel = new Mail();

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
     * Return array of created email files
     *
     * @return array<int, string>
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

    public function getName(): string
    {
        return 'Mail';
    }

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

    public function getToolbarIcon(): string
    {
        return 'mail';
    }

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
     * Save info about messages of current request. Each element is array holding message info, such as: time, reply,
     * bc, cc, from, to and other.
     *
     * @return array<int, array<string, mixed>>
     */
    public function save(): array
    {
        return $this->messages;
    }

    /**
     * @return array<int, array<string, mixed>>|null
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
            return [
                [
                    'value' => $mailCount,
                ],
            ];
        }

        // Current request has no mail. After a Post-Redirect-Get flow the email actually lives in
        // the previous request, so surface a cross-request chip pointing at the panel for that tag.
        // The toolbar JS renders this with a `badge-cross-request` modifier (a small dot underneath
        // the chip) so the dev can tell the data belongs to the request right before this one.
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
     * @param array<string, mixed> $messageData
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

    private function convertParams(mixed $attr): string
    {
        if (is_array($attr)) {
            return implode(', ', array_map(
                static fn(int|string $key): string => (string) $key,
                array_keys($attr),
            ));
        }

        if (is_scalar($attr) || $attr instanceof Stringable) {
            return (string) $attr;
        }

        return '';
    }

    /**
     * Looks at the debug manifest for the request immediately preceding the current one and
     * reports its mail count when non-zero. Returns `null` when no usable previous request exists.
     *
     * @return array{count: int, method: string, shortUrl: string, url: string}|null
     */
    private function findPreviousRequestWithMail(): array|null
    {
        $logTarget = $this->module->logTarget;

        if (!$logTarget instanceof LogTarget) {
            return null;
        }

        try {
            $manifest = $logTarget->loadManifest();
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($manifest) || $manifest === []) {
            return null;
        }

        $currentTag = $this->tag;
        $previousTag = null;
        $found = false;

        foreach ($manifest as $tag => $_summary) {
            if ($found) {
                $previousTag = (string) $tag;
                break;
            }

            if ((string) $tag === (string) $currentTag) {
                $found = true;
            }
        }

        // If the current tag is not in the manifest yet (race during the very first response of a
        // session), fall back to the most-recent entry — that's "previous" from the toolbar's POV.
        if ($previousTag === null) {
            $firstKey = array_key_first($manifest);

            if ($firstKey === null) {
                return null;
            }

            $previousTag = (string) $firstKey;

            if ($previousTag === (string) $currentTag) {
                return null;
            }
        }

        $summary = $manifest[$previousTag] ?? [];
        $summary = is_array($summary) ? $summary : [];

        $dataFile = $this->module->dataPath . '/' . $previousTag . '.data';

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
        $shortUrl = $url === '' ? '' : (string) (parse_url($url, PHP_URL_PATH) ?: $url);

        $panelUrl = \yii\helpers\Url::toRoute([
            '/' . $this->module->getUniqueId() . '/default/view',
            'panel' => $this->id,
            'tag' => $previousTag,
        ]);

        return [
            'count' => $count,
            'method' => $method,
            'shortUrl' => $shortUrl,
            'url' => $panelUrl,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
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
