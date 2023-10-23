<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 *
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yii\debug\panels;

use Symfony\Component\Mime\Part\TextPart;
use Yii;
use yii\base\Event;
use yii\debug\models\search\Mail;
use yii\debug\Panel;
use yii\helpers\FileHelper;
use yii\mail\BaseMailer;
use yii\mail\MailEvent;
use yii\mail\MessageInterface;

use function array_keys;
use function count;
use function file_put_contents;
use function implode;
use function is_array;

/**
 * Debugger panel that collects and displays the generated emails.
 *
 * @property array $messagesFileName
 *
 * @author Mark Jebri <mark.github@yandex.ru>
 *
 * @since 2.0
 */
class MailPanel extends Panel
{
    /**
     * @var string path where all emails will be saved. should be an alias.
     */
    public string $mailPath = '@runtime/debug/mail';

    /**
     * @var array current request sent messages
     */
    private array $_messages = [];

    public function init(): void
    {
        parent::init();

        Event::on(BaseMailer::class, BaseMailer::EVENT_AFTER_SEND, function ($event) {
            /** @var MailEvent $event */
            $message = $event->message;
            /** @var MessageInterface $message */
            $messageData = [
                'isSuccessful' => $event->isSuccessful,
                'from' => $this->convertParams($message->getFrom()),
                'to' => $this->convertParams($message->getTo()),
                'reply' => $this->convertParams($message->getReplyTo()),
                'cc' => $this->convertParams($message->getCc()),
                'bcc' => $this->convertParams($message->getBcc()),
                'subject' => $message->getSubject(),
                'charset' => $message->getCharset(),
            ];

            $this->addMoreInformation($message, $messageData);

            // store message as file
            $fileName = $event->sender->generateMessageFileName();
            $mailPath = Yii::getAlias($this->mailPath);
            FileHelper::createDirectory($mailPath);
            file_put_contents($mailPath . '/' . $fileName, $message->toString());
            $messageData['file'] = $fileName;

            $this->_messages[] = $messageData;
        });
    }

    public function getName(): string
    {
        return 'Mail';
    }

    public function getSummary(): string
    {
        return Yii::$app->view->render('panels/mail/summary', [
            'panel' => $this,
            'mailCount' => is_array($this->data) ? count($this->data) : '⚠',
        ]);
    }

    public function getDetail(): string
    {
        $searchModel = new Mail();
        $dataProvider = $searchModel->search(Yii::$app->request->get(), $this->data);

        return Yii::$app->view->render('panels/mail/detail', [
            'panel' => $this,
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
        ]);
    }

    /**
     * Save info about messages of current request. Each element is array holding message info, such as: time, reply,
     * bc, cc, from, to and other.     */
    public function save(): array
    {
        return $this->_messages;
    }

    /**
     * Return array of created email files.
     */
    public function getMessagesFileName(): array
    {
        $names = [];
        foreach ($this->_messages as $message) {
            $names[] = $message['file'];
        }

        return $names;
    }

    private function convertParams(mixed $attr): string
    {
        if (is_array($attr)) {
            $attr = implode(', ', array_keys($attr));
        }

        if (is_string($attr) === false) {
            $attr = (string) $attr;
        }

        return $attr;
    }

    private function addMoreInformation(MessageInterface $message, array &$messageData): void
    {
        $this->addMoreInformationFromSymfonyMailer($message, $messageData);
    }

    private function addMoreInformationFromSymfonyMailer(MessageInterface $message, array &$messageData): void
    {
        if (!$message instanceof \yii\symfonymailer\Message) {
            return;
        }

        $symfonyMessage = $message->getSymfonyEmail();
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
}
