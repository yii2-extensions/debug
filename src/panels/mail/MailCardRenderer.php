<?php

declare(strict_types=1);

namespace yii\debug\panels\mail;

use UIAwesome\Html\Flow\{Div, Pre};
use UIAwesome\Html\Heading\H2;
use UIAwesome\Html\Helper\Encode;
use UIAwesome\Html\Interactive\{Details, Summary};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Sectioning\Article;
use yii\debug\helpers\{Avatar, Icon};

use function array_map;
use function date;
use function explode;
use function floor;
use function mb_strlen;
use function mb_strtoupper;
use function mb_substr;
use function preg_replace;
use function time;

/**
 * Renders the typed mail message card consumed by the Mail panel detail view's `_item` template.
 *
 * Stateless static helpers: the public entry point takes a typed {@see MailMessage} and returns the rendered
 * `<article>`. Private helpers handle the avatar hue/initials, the body preview, the relative/absolute time labels, and
 * each card section (header, recipients, body, raw-headers details).
 */
final class MailCardRenderer
{
    private const BODY_PREVIEW_LIMIT = 140;

    /**
     * Recipient groups rendered when at least one of the lists is non-empty.
     *
     * @var array<string, array{label: string, getter: string}>
     */
    private const array RECIPIENT_GROUPS = [
        'to' => ['label' => 'TO', 'getter' => 'to'],
        'cc' => ['label' => 'CC', 'getter' => 'cc'],
        'bcc' => ['label' => 'BCC', 'getter' => 'bcc'],
        'reply' => ['label' => 'REPLY-TO', 'getter' => 'replyTo'],
    ];

    /**
     * Renders the full mail message `<article>` element.
     *
     * The caller supplies an URL builder (typically `static fn(string $file) => Url::to(['download-mail', 'file' => $file])`)
     * so the renderer stays free of routing concerns and easy to test in isolation.
     *
     * @param MailMessage $message Typed mail record.
     * @param callable(string): string $downloadUrlBuilder Builds the download URL for the given `.eml` file path.
     */
    public static function renderItem(MailMessage $message, callable $downloadUrlBuilder): string
    {
        $children = [self::renderHead($message, $downloadUrlBuilder)];

        if (self::hasRecipients($message)) {
            $children[] = self::renderRecipients($message);
        }

        $children[] = self::renderBody($message);

        if ($message->headers !== '' || $message->charset !== '') {
            $children[] = self::renderTechDetails($message);
        }

        return Article::tag()
            ->class('yii-debug-mail-card')
            ->html(...$children)
            ->render();
    }

    /**
     * Truncates the body to a single-line preview suitable for the headline area, falling back to `''` when the body
     * is empty.
     */
    private static function bodyPreview(string $body): string
    {
        if ($body === '') {
            return '';
        }

        $collapsed = (string) preg_replace('/\s+/', ' ', $body);

        $preview = mb_substr($collapsed, 0, self::BODY_PREVIEW_LIMIT);

        return mb_strlen($collapsed) > self::BODY_PREVIEW_LIMIT ? "{$preview}…" : $preview;
    }

    /**
     * Formats a Unix timestamp into `[relative, absolute]` strings for the meta time line.
     *
     * The relative form returns `'just now'` for under a minute, `'X min ago'` / `'X h ago'` / `'X d ago'` for the
     * matching thresholds, and falls back to the absolute form past 30 days.
     *
     * @return array{0: string, 1: string} Relative label and absolute label, in that order.
     */
    private static function formatTime(int $unix): array
    {
        $absolute = date('M j, Y · H:i:s', $unix);
        $diff = time() - $unix;

        if ($diff < 60) {
            return ['just now', $absolute];
        }

        if ($diff < 3600) {
            return [(int) floor($diff / 60) . ' min ago', $absolute];
        }

        if ($diff < 86400) {
            return [(int) floor($diff / 3600) . ' h ago', $absolute];
        }

        if ($diff < 2592000) {
            return [(int) floor($diff / 86400) . ' d ago', $absolute];
        }

        return [$absolute, $absolute];
    }

    /**
     * Returns whether the message has at least one populated recipient group.
     *
     * Used to decide whether the recipient block is emitted at all.
     */
    private static function hasRecipients(MailMessage $message): bool
    {
        return $message->to !== [] || $message->cc !== [] || $message->bcc !== [] || $message->replyTo !== [];
    }

    /**
     * Returns the uppercased first letter of the local part of the address, falling back to `'?'` when empty.
     */
    private static function initialsFor(string $email): string
    {
        if ($email === '') {
            return '?';
        }

        $local = explode('@', $email)[0];

        $seed = $local !== '' ? $local : $email;

        return mb_strtoupper(mb_substr($seed, 0, 1));
    }

    /**
     * Returns the recipient list selected by `$getter` (`'to'`, `'cc'`, `'bcc'`, `'replyTo'`).
     *
     * @param string $getter Identifier of the recipient field to read; unknown values yield an empty list.
     *
     * @return list<string> Recipient addresses in declaration order.
     */
    private static function recipientList(MailMessage $message, string $getter): array
    {
        return match ($getter) {
            'to' => $message->to,
            'cc' => $message->cc,
            'bcc' => $message->bcc,
            'replyTo' => $message->replyTo,
            default => [],
        };
    }

    /**
     * Renders the colored avatar `<span>` with the sender's first-letter initial.
     *
     * The hue is derived deterministically from the address, so the same sender always gets the same color.
     */
    private static function renderAvatar(MailMessage $message): Span
    {
        return Span::tag()
            ->addAttribute('style', '--mail-hue: ' . Avatar::hueFor($message->from))
            ->addAriaAttribute('hidden', 'true')
            ->class('yii-debug-mail-avatar')
            ->content(self::initialsFor($message->from));
    }

    /**
     * Renders the body block: the escaped plain-text body, or an empty-state placeholder when the body is empty.
     */
    private static function renderBody(MailMessage $message): Div
    {
        if ($message->body === '') {
            return Div::tag()
                ->class('yii-debug-mail-body yii-debug-mail-body-empty')
                ->content('(empty body)');
        }

        return Div::tag()
            ->class('yii-debug-mail-body')
            ->content($message->body);
    }

    /**
     * Renders the card head: avatar, headline, and meta line with status, time, and the optional download link.
     *
     * @param callable(string): string $downloadUrlBuilder Builds the download URL for the given `.eml` file path.
     */
    private static function renderHead(MailMessage $message, callable $downloadUrlBuilder): Header
    {
        return Header::tag()
            ->class('yii-debug-mail-card-head')
            ->html(
                self::renderAvatar($message),
                self::renderHeadline($message),
                self::renderMeta($message, $downloadUrlBuilder),
            );
    }

    /**
     * Renders the headline block: from line, subject heading, and optional body preview.
     */
    private static function renderHeadline(MailMessage $message): Div
    {
        $children = [
            Span::tag()
                ->class('yii-debug-mail-from')
                ->content($message->from !== '' ? $message->from : '(no sender)'),
            H2::tag()
                ->class('yii-debug-mail-subject')
                ->content($message->subject !== '' ? $message->subject : '(no subject)'),
        ];

        $preview = self::bodyPreview($message->body);

        if ($preview !== '') {
            $children[] = Span::tag()
                ->class('yii-debug-mail-preview')
                ->content($preview);
        }

        return Div::tag()
            ->class('yii-debug-mail-headline')
            ->html(...$children);
    }

    /**
     * Renders the meta line: status pill, relative/absolute time, and the optional `.eml` download link.
     *
     * @param callable(string): string $downloadUrlBuilder Builds the download URL for the given `.eml` file path.
     */
    private static function renderMeta(MailMessage $message, callable $downloadUrlBuilder): Div
    {
        $children = [self::renderStatus($message)];

        if ($message->time !== null) {
            [$relative, $absolute] = self::formatTime($message->time);

            $children[] = Span::tag()
                ->class('yii-debug-mail-time')
                ->content($relative)
                ->title($absolute);
        }

        if ($message->file !== '') {
            $children[] = A::tag()
                ->addAriaAttribute('label', 'Download .eml')
                ->class('yii-debug-mail-download')
                ->href($downloadUrlBuilder($message->file))
                ->html(Icon::render('download'))
                ->title('Download .eml');
        }

        return Div::tag()
            ->class('yii-debug-mail-meta')
            ->html(...$children);
    }

    /**
     * Renders the recipient group block: one row per non-empty list (TO, CC, BCC, REPLY-TO).
     */
    private static function renderRecipients(MailMessage $message): Div
    {
        $rows = [];

        foreach (self::RECIPIENT_GROUPS as $key => $group) {
            $list = self::recipientList($message, $group['getter']);

            if ($list === []) {
                continue;
            }

            $pills = array_map(
                static fn(string $email): Span => Span::tag()
                    ->class('yii-debug-mail-recipient-pill')
                    ->content($email)
                    ->title($email),
                $list,
            );

            $rows[] = Div::tag()
                ->class('yii-debug-mail-recipient-group')
                ->html(
                    Span::tag()
                        ->addDataAttribute('role', $key)
                        ->class('yii-debug-mail-recipient-label')
                        ->content($group['label']),
                    Span::tag()->class('yii-debug-mail-recipient-pills')->html(...$pills),
                );
        }

        return Div::tag()
            ->class('yii-debug-mail-recipients')
            ->html(...$rows);
    }

    /**
     * Renders the status pill (`Sent` / `Failed` with a colored dot).
     */
    private static function renderStatus(MailMessage $message): Span
    {
        $variant = $message->isSuccessful ? 'ok' : 'fail';
        $tooltip = $message->isSuccessful ? 'Mailer reported success' : 'Mailer reported failure';
        $label = $message->isSuccessful ? 'Sent' : 'Failed';

        return Span::tag()
            ->class("yii-debug-mail-status yii-debug-mail-status-{$variant}")
            ->title($tooltip)
            ->html(
                Span::tag()->addAriaAttribute('hidden', 'true')->class('yii-debug-mail-status-dot'),
                ' ' . Encode::content($label),
            );
    }

    /**
     * Renders the raw-headers `<details>` block, collapsed by default.
     *
     * Emitted only when at least one of `headers` / `charset` is populated.
     */
    private static function renderTechDetails(MailMessage $message): Details
    {
        $summaryChildren = [
            Span::tag()
                ->class('yii-debug-mail-tech-icon')
                ->addAriaAttribute('hidden', 'true')
                ->html(Icon::render('code')),
            Span::tag()->class('yii-debug-mail-tech-label')->content('Raw headers'),
        ];

        if ($message->charset !== '') {
            $summaryChildren[] = Span::tag()
                ->class('yii-debug-mail-tech-charset')
                ->title('Charset')
                ->content($message->charset);
        }

        $summaryChildren[] = Span::tag()
            ->addAriaAttribute('hidden', 'true')
            ->class('yii-debug-mail-tech-chevron')
            ->html(Icon::render('chevron-down-thin'));

        return Details::tag()
            ->class('yii-debug-mail-tech')
            ->html(
                Summary::tag()->html(...$summaryChildren),
                Pre::tag()->class('yii-debug-mail-headers')->content($message->headers),
            );
    }
}
