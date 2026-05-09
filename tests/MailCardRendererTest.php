<?php

declare(strict_types=1);

namespace yiiunit\debug;

use PHPUnit\Framework\Attributes\Group;
use yii\debug\panels\mail\{MailCardRenderer, MailMessage};

/**
 * Unit tests for {@see MailCardRenderer} covering the typed mail card composition: avatar / headline / meta line,
 * recipient pills, body block, status pill, time line, download link and the optional raw-headers details block.
 *
 * @copyright Copyright (C) 2026 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
#[Group('panel')]
#[Group('mail')]
final class MailCardRendererTest extends TestCase
{
    public function testRenderItemAvatarFallsBackToFixedHueWhenSenderIsEmpty(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(from: ''),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            '--mail-hue: 210',
            $html,
            "Empty sender must fall back to hue '210'.",
        );
        self::assertStringContainsString(
            '>?<',
            $html,
            "Empty sender must render '?' as the initial.",
        );
    }

    public function testRenderItemEscapesBodyContent(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(body: '<script>alert(1)</script>'),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            '&lt;script&gt;',
            $html,
            'Body must be HTML-escaped.',
        );
        self::assertStringNotContainsString(
            '<script>alert',
            $html,
            'Raw script tags must not leak into the output.',
        );
    }

    public function testRenderItemOmitsBodyPreviewWhenBodyIsEmpty(): void
    {
        self::assertStringNotContainsString(
            'yii-debug-mail-preview',
            MailCardRenderer::renderItem(self::makeMessage(body: ''), self::makeUrlBuilder()),
            'Empty body must omit the preview span.',
        );
    }

    public function testRenderItemOmitsDownloadLinkWhenFileIsEmpty(): void
    {
        self::assertStringNotContainsString(
            'yii-debug-mail-download',
            MailCardRenderer::renderItem(self::makeMessage(file: ''), self::makeUrlBuilder()),
            'Empty file must omit the download link.',
        );
    }

    public function testRenderItemOmitsRecipientBlockWhenAllListsAreEmpty(): void
    {
        self::assertStringNotContainsString(
            'yii-debug-mail-recipients',
            MailCardRenderer::renderItem(self::makeMessage(), self::makeUrlBuilder()),
            'Empty recipient lists must omit the block.',
        );
    }

    public function testRenderItemOmitsTechDetailsWhenBothHeadersAndCharsetAreEmpty(): void
    {
        self::assertStringNotContainsString(
            'yii-debug-mail-tech',
            MailCardRenderer::renderItem(self::makeMessage(headers: '', charset: ''), self::makeUrlBuilder()),
            'Empty headers and charset must omit the tech details.',
        );
    }

    public function testRenderItemOmitsTimeWhenNull(): void
    {
        self::assertStringNotContainsString(
            'yii-debug-mail-time',
            MailCardRenderer::renderItem(self::makeMessage(time: null), self::makeUrlBuilder()),
            "'null' time must omit the time span.",
        );
    }

    public function testRenderItemRendersAvatarHueDeterministicallyFromSender(): void
    {
        $first = MailCardRenderer::renderItem(
            self::makeMessage(from: 'a@example.com'),
            self::makeUrlBuilder(),
        );
        $second = MailCardRenderer::renderItem(
            self::makeMessage(from: 'a@example.com'),
            self::makeUrlBuilder(),
        );
        $third = MailCardRenderer::renderItem(
            self::makeMessage(from: 'b@example.com'),
            self::makeUrlBuilder(),
        );

        self::assertMatchesRegularExpression(
            '/--mail-hue: \d+/',
            $first,
            'Avatar must carry an inline hue style.',
        );
        self::assertSame(
            self::extractHue($first),
            self::extractHue($second),
            'Same sender must produce the same hue.',
        );
        self::assertNotSame(
            self::extractHue($first),
            self::extractHue($third),
            'Different senders must produce different hues.',
        );
    }

    public function testRenderItemRendersBodyPreviewTruncatedAt140Characters(): void
    {
        $longBody = str_repeat('Lorem ipsum dolor sit amet, ', 20);

        $html = MailCardRenderer::renderItem(
            self::makeMessage(body: $longBody),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            'class="yii-debug-mail-preview"',
            $html,
            'Preview span must be present when body is non-empty.',
        );
        self::assertStringContainsString(
            '…',
            $html,
            'Long previews must end with an ellipsis.',
        );
    }

    public function testRenderItemRendersDownloadLinkWhenFileIsSet(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(file: '/tmp/mail.eml'),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            'class="yii-debug-mail-download"',
            $html,
            'Download link must carry the dedicated class.',
        );
        self::assertStringContainsString(
            'href="/debug/download-mail?file=/tmp/mail.eml"',
            $html,
            'Download href must round-trip the URL builder output.',
        );
    }

    public function testRenderItemRendersEmptyBodyPlaceholderWhenBodyIsEmpty(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(body: ''),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            'yii-debug-mail-body-empty',
            $html,
            'Empty body must use the empty-body modifier.',
        );
        self::assertStringContainsString(
            '(empty body)',
            $html,
            'Empty body placeholder must be visible.',
        );
    }

    public function testRenderItemRendersFallbackPlaceholdersWhenFromOrSubjectAreEmpty(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(from: '', subject: ''),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            '(no sender)',
            $html,
            "Empty from must fall back to '(no sender)'.",
        );
        self::assertStringContainsString(
            '(no subject)',
            $html,
            "Empty subject must fall back to '(no subject)'.",
        );
    }

    public function testRenderItemRendersFromAndSubject(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(from: 'sender@example.com', subject: 'Welcome'),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            'sender@example.com',
            $html,
            'Sender address must be visible.',
        );
        self::assertStringContainsString(
            'Welcome',
            $html,
            'Subject must be visible.',
        );
    }

    public function testRenderItemRendersRecipientGroupsWithLabelsAndPills(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(
                to: ['a@example.com', 'b@example.com'],
                cc: ['cc@example.com'],
                bcc: ['bcc@example.com'],
                replyTo: ['reply@example.com'],
            ),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            'class="yii-debug-mail-recipients"',
            $html,
            'Recipients wrapper must be present.',
        );
        self::assertStringContainsString(
            'TO',
            $html,
            'TO label must be present.',
        );
        self::assertStringContainsString(
            'CC',
            $html,
            'CC label must be present.',
        );
        self::assertStringContainsString(
            'BCC',
            $html,
            'BCC label must be present.',
        );
        self::assertStringContainsString(
            'REPLY-TO',
            $html,
            'REPLY-TO label must be present.',
        );
        self::assertStringContainsString(
            'a@example.com',
            $html,
            'TO pill must include the address.',
        );
        self::assertStringContainsString(
            'cc@example.com',
            $html,
            'CC pill must include the address.',
        );
    }

    public function testRenderItemRendersStatusFailWhenIsSuccessfulIsFalse(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(isSuccessful: false),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            'yii-debug-mail-status-fail',
            $html,
            'Failed messages must use the `fail` variant.',
        );
        self::assertStringContainsString(
            'Failed',
            $html,
            'Status label must read `Failed`.',
        );
    }

    public function testRenderItemRendersStatusOkWhenIsSuccessfulIsTrue(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(isSuccessful: true),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            'yii-debug-mail-status-ok',
            $html,
            'Successful messages must use the `ok` variant.',
        );
        self::assertStringContainsString(
            'Sent',
            $html,
            'Status label must read `Sent`.',
        );
    }

    public function testRenderItemRendersTechDetailsWhenHeadersOrCharsetSet(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(headers: 'X-Foo: bar', charset: 'UTF-8'),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            'class="yii-debug-mail-tech"',
            $html,
            'Tech details wrapper must be present.',
        );
        self::assertStringContainsString(
            'Raw headers',
            $html,
            'Tech summary label must be present.',
        );
        self::assertStringContainsString(
            'X-Foo: bar',
            $html,
            'Header content must be visible.',
        );
        self::assertStringContainsString(
            'UTF-8',
            $html,
            'Charset must be visible in the summary.',
        );
    }

    public function testRenderItemRendersTimeWhenSet(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(time: 1_700_000_000),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            'class="yii-debug-mail-time"',
            $html,
            'Time span must carry the dedicated class.',
        );
        self::assertMatchesRegularExpression(
            '/title="[A-Z][a-z]{2} \d/',
            $html,
            'Time tooltip must contain the formatted absolute date.'
        );
    }

    public function testRenderItemRendersUppercasedFirstLetterOfLocalPartAsInitial(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(from: 'wilmer@example.com'),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            '>W<',
            $html,
            'Initial must be the uppercased first letter of the local part.',
        );
    }

    public function testRenderItemWrapsContentInArticleWithMailCardClass(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            'class="yii-debug-mail-card"',
            $html,
            'Outer wrapper class must be present.',
        );
        self::assertStringContainsString(
            'class="yii-debug-mail-card-head"',
            $html,
            'Head wrapper class must be present.',
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockWebApplication();
    }

    /**
     * Extracts the avatar hue value from rendered HTML for hue-stability assertions.
     */
    private static function extractHue(string $html): int
    {
        if (preg_match('/--mail-hue: (\d+)/', $html, $m) === 1) {
            return (int) $m[1];
        }

        self::fail('No avatar hue found in rendered HTML.');
    }

    /**
     * @param list<string> $to
     * @param list<string> $cc
     * @param list<string> $bcc
     * @param list<string> $replyTo
     */
    private static function makeMessage(
        string $from = '',
        array $to = [],
        array $cc = [],
        array $bcc = [],
        array $replyTo = [],
        string $subject = 'Test subject',
        string $body = 'Test body',
        string $headers = '',
        string $charset = '',
        string $file = '',
        bool $isSuccessful = true,
        int|null $time = null,
    ): MailMessage {
        return new MailMessage(
            from: $from,
            to: $to,
            cc: $cc,
            bcc: $bcc,
            replyTo: $replyTo,
            subject: $subject,
            body: $body,
            headers: $headers,
            charset: $charset,
            file: $file,
            isSuccessful: $isSuccessful,
            time: $time,
        );
    }

    /**
     * Builds a deterministic download-URL builder so tests can assert the rendered href without needing an active
     * controller context.
     *
     * @return callable(string): string
     */
    private static function makeUrlBuilder(): callable
    {
        return static fn(string $file): string => "/debug/download-mail?file={$file}";
    }
}
