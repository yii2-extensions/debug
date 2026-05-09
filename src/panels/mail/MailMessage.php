<?php

declare(strict_types=1);

namespace yii\debug\panels\mail;

/**
 * Typed view-model for a single mail message rendered in the Mail panel detail view.
 *
 * Mirrors the captured `BaseMailer::EVENT_AFTER_SEND` payload after every value has been narrowed and the recipient
 * fields split into per-address lists, so the consuming view iterates and reads typed properties without further
 * runtime checks.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
final readonly class MailMessage
{
    public function __construct(
        /**
         * Sender address as captured (typically `name@example.com` or `Name <name@example.com>`).
         */
        public string $from,
        /**
         * @var list<string> Primary recipients split out of the comma-separated `to` field, with empty entries
         * dropped.
         */
        public array $to,
        /**
         * @var list<string> Carbon-copy recipients split out of the comma-separated `cc` field.
         */
        public array $cc,
        /**
         * @var list<string> Blind carbon-copy recipients split out of the comma-separated `bcc` field.
         */
        public array $bcc,
        /**
         * @var list<string> Reply-to addresses split out of the comma-separated `reply` field.
         */
        public array $replyTo,
        /**
         * Subject line as captured.
         */
        public string $subject,
        /**
         * Plain-text body as captured. Empty string when the message had no body.
         */
        public string $body,
        /**
         * Raw RFC-5322 headers as captured by the mailer, joined with line breaks.
         */
        public string $headers,
        /**
         * Charset declared on the message, or empty string when none was set.
         */
        public string $charset,
        /**
         * Path to the persisted `.eml` file, or empty string when the mailer does not expose one.
         */
        public string $file,
        /**
         * `true` when the mailer reported the message as sent, `false` when it reported a failure.
         */
        public bool $isSuccessful,
        /**
         * Capture timestamp as a Unix epoch second, or `null` when the original payload had no parseable time.
         */
        public int|null $time,
    ) {}
}
